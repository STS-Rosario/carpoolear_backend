<?php

namespace Tests\Unit\Helpers;

use App\Helpers\DocumentIdHelper;
use Tests\TestCase;

class DocumentIdHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'carpoolear.profile_id_format' => '##.###.###',
            'carpoolear.profile_id_formats' => [
                ['type' => 'dni', 'pattern' => '##.###.###'],
                ['type' => 'passport', 'pattern' => 'A########'],
            ],
        ]);
    }

    public function test_normalize_for_storage_strips_dni_separators(): void
    {
        $this->assertSame('30123456', DocumentIdHelper::normalizeForStorage('30.123.456'));
    }

    public function test_normalize_for_storage_accepts_passport_values(): void
    {
        $this->assertSame('A33070219', DocumentIdHelper::normalizeForStorage('a33070219'));
    }

    public function test_normalize_for_storage_rejects_values_outside_allowed_masks(): void
    {
        $this->assertNull(DocumentIdHelper::normalizeForStorage('ABC123'));
    }

    public function test_normalize_for_ban_check_keeps_passport_prefix(): void
    {
        $this->assertSame('A33070219', DocumentIdHelper::normalizeForBanCheck('A33070219'));
    }

    public function test_normalize_for_ban_check_strips_dni_separators(): void
    {
        $this->assertSame('30123456', DocumentIdHelper::normalizeForBanCheck('30.123.456'));
    }
}
