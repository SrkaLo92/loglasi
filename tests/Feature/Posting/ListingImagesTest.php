<?php

namespace Tests\Feature\Posting;

use App\Models\ListingImage;
use App\Services\ListingImages;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ListingImagesTest extends PostingTestCase
{
    private function service(): ListingImages
    {
        return app(ListingImages::class);
    }

    private function uploadError(string $file, ?string $as = null): string
    {
        try {
            $this->service()->upload($this->image($file, $as), $this->user());
        } catch (ValidationException $e) {
            return $e->errors()['file'][0];
        }
        $this->fail('Upload should have been refused.');
    }

    public function test_jpeg_is_rotated_by_exif_and_exif_is_removed(): void
    {
        $original = file_get_contents(base_path('tests/Fixtures/images/rotated-exif.jpg'));
        $this->assertStringContainsString('TestCam', $original);

        $image = $this->service()->upload($this->image('rotated-exif.jpg'), $this->user());

        $this->assertStringStartsWith('oglasi/tmp/', $image->path);
        $stored = Storage::disk('public')->get($image->path);
        $this->assertStringNotContainsString('TestCam', $stored);
        $this->assertStringNotContainsString('Exif', $stored);
        // 300×200 with orientation 6 (rotate 90° clockwise) becomes 200×300
        $this->assertSame([200, 300], [$image->width, $image->height]);
        $size = getimagesizefromstring($stored);
        $this->assertSame([200, 300], [$size[0], $size[1]]);
        // left half red becomes the top half
        $gd = imagecreatefromstring($stored);
        $top = imagecolorat($gd, 100, 40);
        $this->assertGreaterThan(150, ($top >> 16) & 0xFF);
        $this->assertSame(40, strlen($image->hash));
        $this->assertSame(16, strlen($image->phash));
    }

    public function test_variants_are_generated_in_webp_and_jpeg(): void
    {
        $image = $this->service()->upload($this->image('large.jpg'), $this->user())->refresh();

        $this->assertSame(['sm', 'md', 'lg'], array_keys($image->variants));
        foreach (['sm' => 400, 'md' => 800, 'lg' => 1600] as $name => $width) {
            $this->assertSame(['webp', 'jpg', 'width'], array_keys($image->variants[$name]));
            $variant = $image->variants[$name];
            $this->assertSame($width, $variant['width']);
            foreach (['webp' => IMAGETYPE_WEBP, 'jpg' => IMAGETYPE_JPEG] as $format => $type) {
                $data = Storage::disk('public')->get($variant[$format]);
                $info = getimagesizefromstring($data);
                $this->assertSame($type, $info[2], "$name $format");
                $this->assertSame($width, $info[0]);
            }
        }
        $this->assertSame([1800, 1200], [$image->width, $image->height]);
        $api = $this->service()->toApi($image);
        $this->assertStringEndsWith('-sm.webp', $api['thumb']);
        $this->assertStringEndsWith('-lg.webp', $api['large']);
    }

    public function test_small_image_is_not_enlarged(): void
    {
        $image = $this->service()->upload($this->image('photo.webp'), $this->user())->refresh();

        $this->assertStringEndsWith('.webp', $image->path);
        $this->assertSame(320, $image->variants['lg']['width']);
        $this->assertSame(320, $image->variants['sm']['width']);
    }

    public function test_png_with_transparency_gets_white_background_in_jpeg(): void
    {
        $image = $this->service()->upload($this->image('alpha.png'), $this->user())->refresh();

        $this->assertStringEndsWith('.png', $image->path);
        $jpg = imagecreatefromstring(Storage::disk('public')->get($image->variants['sm']['jpg']));
        $corner = imagecolorat($jpg, 1, 1);
        $this->assertSame([255, 255, 255], [($corner >> 16) & 0xFF, ($corner >> 8) & 0xFF, $corner & 0xFF]);
    }

    public function test_heic_is_refused_with_a_clear_message(): void
    {
        $this->assertStringContainsString('HEIC', $this->uploadError('iphone.heic'));
        $this->assertSame(0, ListingImage::count());
    }

    public function test_file_that_is_not_an_image_is_refused(): void
    {
        $this->assertSame('Fajl nije slika u formatu JPG, PNG ili WebP.', $this->uploadError('not-image.jpg'));
    }

    public function test_tiny_image_is_refused(): void
    {
        $this->assertStringContainsString('premala', $this->uploadError('tiny.png'));
    }

    public function test_size_limit_comes_from_settings(): void
    {
        Settings::set('image_max_mb', 0.02);

        $this->assertSame('Slika je prevelika. Najveća dozvoljena veličina je 0,02 MB.', $this->uploadError('large.jpg'));
    }

    public function test_listing_can_have_at_most_the_configured_number_of_images(): void
    {
        Settings::set('listing_max_images', 2);
        $user = $this->user();
        $listing = $this->draft($user);
        $this->service()->upload($this->image('stripes.jpg'), $user, $listing);
        $this->service()->upload($this->image('photo.webp'), $user, $listing);

        $this->expectException(ValidationException::class);
        $this->service()->upload($this->image('alpha.png'), $user, $listing);
    }

    public function test_images_are_stored_under_listing_folder_and_moved_from_tmp(): void
    {
        $user = $this->user();
        $listing = $this->draft($user);
        $direct = $this->service()->upload($this->image('stripes.jpg'), $user, $listing);
        $this->assertStringStartsWith('oglasi/'.$listing->id.'/', $direct->path);

        $tmp = $this->service()->upload($this->image('photo.webp'), $user)->refresh();
        $this->service()->sync($listing, $user, [$tmp->id, $direct->id]);

        $tmp->refresh();
        $this->assertSame($listing->id, $tmp->listing_id);
        $this->assertStringStartsWith('oglasi/'.$listing->id.'/', $tmp->path);
        $this->assertStringStartsWith('oglasi/'.$listing->id.'/', $tmp->variants['md']['webp']);
        Storage::disk('public')->assertExists([$tmp->path, $tmp->variants['md']['webp'], $tmp->variants['sm']['jpg']]);
        $this->assertSame([$tmp->id, $direct->id], $listing->images()->pluck('id')->all());
    }

    public function test_sync_refuses_images_of_another_user(): void
    {
        $listing = $this->draft($this->user());
        $foreign = $this->service()->upload($this->image('stripes.jpg'), $this->user());

        $this->expectException(ValidationException::class);
        $this->service()->sync($listing, $listing->user, [$foreign->id]);
    }

    public function test_reorder_cover_move_and_delete(): void
    {
        $user = $this->user();
        $listing = $this->draft($user);
        $a = $this->service()->upload($this->image('stripes.jpg'), $user, $listing);
        $b = $this->service()->upload($this->image('photo.webp'), $user, $listing);
        $c = $this->service()->upload($this->image('alpha.png'), $user, $listing)->refresh();
        $ids = fn () => $listing->images()->pluck('id')->all();
        $this->assertSame([$a->id, $b->id, $c->id], $ids());

        $this->service()->setCover($listing, $c);
        $this->assertSame([$c->id, $a->id, $b->id], $ids());

        $this->service()->move($listing, $b, -1);
        $this->assertSame([$c->id, $b->id, $a->id], $ids());

        $this->service()->reorder($listing, [$a->id]);
        $this->assertSame([$a->id, $c->id, $b->id], $ids());

        $files = [$c->path, $c->variants['sm']['webp'], $c->variants['lg']['jpg']];
        $this->service()->delete($c);
        Storage::disk('public')->assertMissing($files);
        $this->assertSame([$a->id, $b->id], $ids());
        $this->assertSame([0, 1], $listing->images()->pluck('sort')->all());
    }

    public function test_command_generates_missing_variants_for_imported_images(): void
    {
        $listing = $this->draft($this->user());
        Storage::disk('public')->put('oglasi/'.$listing->id.'/55-slika.jpg', file_get_contents(base_path('tests/Fixtures/images/large.jpg')));
        $imported = ListingImage::create(['listing_id' => $listing->id, 'path' => 'oglasi/'.$listing->id.'/55-slika.jpg', 'wp_attachment_id' => 55]);
        $missing = ListingImage::create(['listing_id' => $listing->id, 'path' => 'oglasi/'.$listing->id.'/nema.jpg']);

        $this->artisan('images:variants', ['--batch' => 10])->assertSuccessful();

        $imported->refresh();
        $this->assertSame(800, $imported->variants['md']['width']);
        $this->assertSame([1800, 1200], [$imported->width, $imported->height]);
        $this->assertNotNull($imported->hash);
        Storage::disk('public')->assertExists('oglasi/'.$listing->id.'/55-slika-md.webp');
        // a missing file is marked as tried so it does not block later batches
        $this->assertSame([], $missing->refresh()->variants);
    }

    public function test_palette_png_gets_variants(): void
    {
        $listing = $this->draft($this->user());
        $gd = imagecreate(900, 600);
        imagecolorallocate($gd, 30, 120, 90);
        ob_start();
        imagepng($gd);
        Storage::disk('public')->put('oglasi/'.$listing->id.'/paleta.png', ob_get_clean());
        $image = ListingImage::create(['listing_id' => $listing->id, 'path' => 'oglasi/'.$listing->id.'/paleta.png']);

        $this->assertTrue($this->service()->generateVariants($image));

        $this->assertSame(800, $image->refresh()->variants['md']['width']);
        Storage::disk('public')->assertExists($image->variants['md']['webp']);
    }

    public function test_prune_removes_old_unattached_uploads(): void
    {
        $user = $this->user();
        $old = $this->service()->upload($this->image('stripes.jpg'), $user);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        $fresh = $this->service()->upload($this->image('photo.webp'), $user);

        $this->artisan('images:prune')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);
        Storage::disk('public')->assertMissing($old->path);
    }
}
