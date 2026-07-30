<?php

namespace App\Interfaces;

interface JobSourceAdapterInterface
{
    /**
     * اسم مصدر الوظائف.
     */
    public function sourceName(): string;

    /**
     * جلب صفحة واحدة وتحويل إعلاناتها إلى الشكل الموحد.
     *
     * @return array{
     *     jobs: array<int, array<string, mixed>>,
     *     current_page: int,
     *     has_more: bool
     * }
     */
    public function fetchPage(int $page = 1): array;
}
