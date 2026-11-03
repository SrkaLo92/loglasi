<?php

namespace App\Support\Seo;

use App\Models\Category;

/**
 * Search phrases for categories whose old WordPress names read „Stanovi | prodaja” or „Stanovi – iznajmljivanje”:
 * „Prodaja stanova”, „Izdavanje stanova”. Fills only an empty phrase; an old title or H1 with „|” is cleared so the
 * phrase is used (texts written in the admin without „|” stay).
 */
final class CategoryPhrases
{
    /** Genitive plural of the things that are sold or rented. */
    private const GENITIVE = [
        'stanovi' => 'stanova', 'stan' => 'stanova', 'kuće' => 'kuća', 'kuce' => 'kuća', 'kuća' => 'kuća',
        'apartmani' => 'apartmana', 'apartman' => 'apartmana', 'poslovni prostor' => 'poslovnog prostora',
        'poslovni prostori' => 'poslovnog prostora', 'lokali' => 'lokala', 'lokal' => 'lokala',
        'placevi' => 'placeva', 'plac' => 'placeva', 'plac i zemljište' => 'placeva i zemljišta', 'zemljište' => 'zemljišta',
        'placevi i zemljište' => 'placeva i zemljišta', 'vikendice' => 'vikendica', 'garaže' => 'garaža', 'garaze' => 'garaža',
        'sobe' => 'soba', 'magacini' => 'magacina', 'hale' => 'hala',
    ];

    /** The deal, as the noun people type. */
    private const DEAL = [
        'prodaja' => 'Prodaja', 'izdavanje' => 'Izdavanje', 'iznajmljivanje' => 'Izdavanje', 'izdaje se' => 'Izdavanje',
        'zakup' => 'Zakup', 'kupovina' => 'Kupovina', 'kupujem' => 'Kupovina',
    ];

    /** Phrase for a category name, or null when the name is not a „thing | deal” pair. */
    public static function phraseFor(string $name): ?string
    {
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = preg_split('/\s*(?:\||–|—|\s-\s)\s*/u', mb_strtolower(trim($name))) ?: [];
        if (count($parts) !== 2) {
            return null;
        }
        foreach ([[$parts[0], $parts[1]], [$parts[1], $parts[0]]] as [$thing, $deal]) {
            if (isset(self::GENITIVE[$thing], self::DEAL[$deal])) {
                return self::DEAL[$deal].' '.self::GENITIVE[$thing];
            }
        }

        return null;
    }

    /** @return list<string> lines „slug: phrase” of what changed (or would change with $dry) */
    public static function apply(bool $dry = false): array
    {
        $changed = [];
        foreach (Category::query()->where(fn ($q) => $q->whereNull('seo_phrase')->orWhere('seo_phrase', ''))->get() as $category) {
            $phrase = self::phraseFor((string) $category->name);
            if ($phrase === null) {
                continue;
            }
            $changed[] = $category->slug.': '.$phrase;
            if ($dry) {
                continue;
            }
            $category->seo_phrase = $phrase;
            foreach (['seo_title', 'seo_h1'] as $column) {
                $text = preg_replace('/\s*\|\s*loglasi\.rs\s*$/u', '', (string) $category->{$column});
                if (str_contains((string) $text, '|')) {
                    $category->{$column} = null;
                }
            }
            $category->save();
        }

        return $changed;
    }
}
