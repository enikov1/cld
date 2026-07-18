<?php

namespace Tests\Unit;

use App\Support\Speedbar;
use Tests\TestCase;

class SpeedbarJsonLdTest extends TestCase
{
    public function test_json_ld_escapes_script_breakout_in_search_query(): void
    {
        $json = Speedbar::forSearch('</script><script>alert(1)</script>', 1)->toJsonLd();

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertStringContainsString('\u003C', $json);
    }
}
