<?php

namespace Tests\Unit\MarketAnalysis;

use App\Services\MarketAnalysis\MarketTextNormalizer;
use Tests\TestCase;

class MarketTextNormalizerTest extends TestCase
{
    public function test_it_preserves_technical_tokens_with_symbols(): void
    {
        $normalizer = new MarketTextNormalizer();

        $result = $normalizer->normalize(
            'C++, C#, .NET, ASP.NET, Node.js, Next.js, '
            . 'Vue.js, React.js, Express.js'
        );

        $this->assertSame(
            'cpp csharp dotnet aspnet nodejs nextjs '
            . 'vuejs reactjs expressjs',
            $result
        );
    }

    public function test_it_normalizes_technical_name_variants(): void
    {
        $normalizer = new MarketTextNormalizer();

        $result = $normalizer->normalize(
            'Node JS nodejs NEXTJS Vue JS'
        );

        $this->assertSame(
            'nodejs nodejs nextjs vuejs',
            $result
        );
    }

    public function test_it_keeps_existing_arabic_and_separator_behavior(): void
    {
        $normalizer = new MarketTextNormalizer();

        $result = $normalizer->normalize(
            'إدارة الأنظمة / Laravel | React'
        );

        $this->assertSame(
            'اداره الانظمه laravel react',
            $result
        );
    }

    public function test_it_decodes_html_entities_before_normalization(): void
    {
        $normalizer = new MarketTextNormalizer();

        $result = $normalizer->normalize(
            'Node&#46;js &amp; C&#35;'
        );

        $this->assertSame(
            'nodejs csharp',
            $result
        );
    }
}
