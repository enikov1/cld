<?php

namespace Tests\Unit;

use App\Support\EncryptedSiteSecret;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EncryptedSiteSecretTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypts_and_decrypts_secret(): void
    {
        EncryptedSiteSecret::set('test_secret', 'super-secret');

        $raw = SiteSetting::query()->where('key', 'test_secret')->value('value');
        $this->assertIsString($raw);
        $this->assertStringStartsWith('enc:v1:', $raw);
        $this->assertSame('super-secret', EncryptedSiteSecret::get('test_secret'));
    }

    public function test_reads_legacy_plaintext(): void
    {
        SiteSetting::set('legacy_secret', 'plain-value');

        $this->assertSame('plain-value', EncryptedSiteSecret::get('legacy_secret'));
    }

    public function test_corrupt_ciphertext_returns_empty(): void
    {
        SiteSetting::set('bad_secret', 'enc:v1:' . Crypt::encryptString('x') . 'tampered');

        $this->assertSame('', EncryptedSiteSecret::get('bad_secret'));
    }
}
