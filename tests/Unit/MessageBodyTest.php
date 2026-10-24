<?php

namespace Tests\Unit;

use App\Services\Messages\MessageBody;
use PHPUnit\Framework\TestCase;

class MessageBodyTest extends TestCase
{
    public function test_clean_removes_tags_but_keeps_text(): void
    {
        $this->assertSame('Volim ovaj bicikl <3', MessageBody::clean('Volim <b>ovaj</b> bicikl <3'));
        $this->assertSame('a < b i c > d', MessageBody::clean('a < b i c > d'));
        $this->assertSame('Zdravo', MessageBody::clean('<script>alert(1)</script>Zdravo<style>p{}</style><!-- x -->'));
        $this->assertSame("Prvi red\n\nDrugi red", MessageBody::clean("  Prvi red   \r\n\r\n\r\n\r\nDrugi red\u{0007}  "));
        $this->assertSame('Porodica 👨‍👩‍👧 i šđčćž', MessageBody::clean('Porodica 👨‍👩‍👧 i šđčćž'));
        $this->assertSame('', MessageBody::clean('<p><br></p>'));
    }

    public function test_html_escapes_and_links_with_nofollow(): void
    {
        $html = (string) MessageBody::html("Vidi www.primer.rs/oglas?x=1&y=2, i <b>to</b>\njavascript:alert(1)");

        $this->assertStringContainsString('<a href="https://www.primer.rs/oglas?x=1&amp;y=2" rel="nofollow ugc noopener" target="_blank">www.primer.rs/oglas?x=1&amp;y=2</a>,', $html);
        $this->assertStringContainsString('&lt;b&gt;to&lt;/b&gt;<br>', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
    }

    public function test_excerpt(): void
    {
        $this->assertSame('Jedan dva tri', MessageBody::excerpt("Jedan\n\ndva   tri"));
        $this->assertSame('abcde…', MessageBody::excerpt('abcdefgh', 5));
    }
}
