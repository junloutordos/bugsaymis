<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DigitalSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DigitalSignatureServiceAssertPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_op_when_user_has_no_pin_set(): void
    {
        $user = User::factory()->create(['signature_pin' => null]);
        (new DigitalSignatureService())->assertSigningPin($user, null);
        $this->assertTrue(true);
    }

    public function test_throws_when_pin_set_but_none_given(): void
    {
        $user = User::factory()->create(['signature_pin' => Hash::make('123456')]);
        $this->expectException(ValidationException::class);
        (new DigitalSignatureService())->assertSigningPin($user, null);
    }

    public function test_throws_when_pin_set_and_wrong_pin_given(): void
    {
        $user = User::factory()->create(['signature_pin' => Hash::make('123456')]);
        $this->expectException(ValidationException::class);
        (new DigitalSignatureService())->assertSigningPin($user, '000000');
    }

    public function test_passes_when_correct_pin_given(): void
    {
        $user = User::factory()->create(['signature_pin' => Hash::make('123456')]);
        (new DigitalSignatureService())->assertSigningPin($user, '123456');
        $this->assertTrue(true);
    }
}
