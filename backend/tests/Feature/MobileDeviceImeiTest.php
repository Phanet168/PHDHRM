<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AuthController;
use App\Models\MobileDeviceRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phones sign in by their IMEI (typed in once, since Android 10+ hides it).
 * The app used to send the Android build code — shared by every phone on the
 * same firmware — so an approved registration under that old number moves to
 * the IMEI at the next sign-in. Runs on the dev database in a rolled-back
 * transaction.
 */
class MobileDeviceImeiTest extends TestCase
{
    use DatabaseTransactions;

    private const LEGACY = 'ANDROID-BP2A.250605.031.A3';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'full_name' => 'PHPUnit IMEI',
            'email' => 'phpunit_imei_'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('secret-pass'),
            'user_type_id' => 3,
        ]);
    }

    /** A valid IMEI: the 14 digits plus their Luhn check digit. */
    private static function imei(string $body): string
    {
        for ($check = 0; $check <= 9; $check++) {
            if (AuthController::isImei($body.$check)) {
                return $body.$check;
            }
        }
        throw new \LogicException('no check digit');
    }

    private function register(string $deviceId, string $status = 'active', ?string $imei = null): MobileDeviceRegistration
    {
        return MobileDeviceRegistration::create([
            'user_id' => $this->user->id, 'device_id' => $deviceId, 'device_name' => $deviceId,
            'platform' => 'android', 'imei' => $imei ?? $deviceId, 'status' => $status,
        ]);
    }

    private function login(string $deviceId, ?string $imei, ?string $legacy = null)
    {
        return $this->postJson('/api/auth/login', array_filter([
            'email' => $this->user->email, 'password' => 'secret-pass',
            'device_id' => $deviceId, 'token_id' => $deviceId, 'device_name' => $deviceId,
            'imei' => $imei, 'legacy_device_id' => $legacy,
        ]));
    }

    public function test_imei_check_digit(): void
    {
        $this->assertTrue(AuthController::isImei('490154203237518'));
        $this->assertFalse(AuthController::isImei('490154203237519'));
        $this->assertFalse(AuthController::isImei(self::LEGACY));
        $this->assertFalse(AuthController::isImei('000000000000000'));
    }

    public function test_an_approved_phone_moves_from_the_build_code_to_its_imei(): void
    {
        $legacy = $this->register(self::LEGACY);
        $imei = self::imei('35209900176148');

        $this->login($imei, $imei, self::LEGACY)->assertOk()->assertJsonPath('status', 'ok');
        $legacy->refresh();
        $this->assertSame($imei, $legacy->device_id);
        $this->assertSame($imei, $legacy->imei);

        // From now on the IMEI alone signs in; the old number no longer does.
        $this->login($imei, $imei)->assertOk();
        $this->login(self::LEGACY, null)->assertForbidden()->assertJsonPath('code', 'device_not_registered');
    }

    public function test_a_mistyped_imei_or_unapproved_old_number_does_not_move(): void
    {
        $this->register(self::LEGACY);
        $this->login('352099001761489', '352099001761489', self::LEGACY)
            ->assertForbidden()->assertJsonPath('code', 'device_not_registered');

        MobileDeviceRegistration::where('user_id', $this->user->id)->update(['status' => 'pending']);
        $imei = self::imei('35209900176148');
        $this->login($imei, $imei, self::LEGACY)->assertForbidden()->assertJsonPath('code', 'device_not_registered');
        $this->assertSame(self::LEGACY, MobileDeviceRegistration::where('user_id', $this->user->id)->value('device_id'));
    }

    public function test_a_registered_imei_must_match_and_old_junk_is_replaced(): void
    {
        $imei = self::imei('35209900176148');
        $other = self::imei('86012304567890');
        // Stored "imei" holds the old app number: not a real IMEI, so no mismatch.
        $device = $this->register($imei, imei: self::LEGACY);
        $this->login($imei, $imei)->assertOk();
        $this->assertSame($imei, $device->fresh()->imei);

        $this->login($imei, $other)->assertForbidden()->assertJsonPath('code', 'device_imei_mismatch');
    }

    public function test_a_device_request_records_the_imei(): void
    {
        $imei = self::imei('35209900176148');
        $this->postJson('/api/auth/device-request', [
            'email' => $this->user->email, 'password' => 'secret-pass',
            'device_id' => $imei, 'imei' => '35 209900 176148 '.substr($imei, -1),
        ])->assertOk()->assertJsonPath('code', 'device_request_submitted');
        $row = MobileDeviceRegistration::where('user_id', $this->user->id)->first();
        $this->assertSame($imei, $row->device_id);
        $this->assertSame($imei, $row->imei);
        $this->assertSame('pending', $row->status);
    }
}
