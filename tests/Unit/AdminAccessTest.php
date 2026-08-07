<?php

namespace Tests\Unit;

use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.token' => 'test-admin-token-value']);
        cache()->store('admin')->flush();
    }

    public function test_header_token_is_accepted(): void
    {
        $request = Request::create('/api/admin/stats', 'GET');
        $request->headers->set('X-ADMIN-TOKEN', 'test-admin-token-value');

        $this->assertTrue(AdminAccess::hasValidToken($request));
    }

    public function test_opaque_session_cookie_is_accepted(): void
    {
        $cookie = AdminAccess::makeCookie();
        $this->assertNotNull($cookie);

        $request = Request::create('/api/admin/stats', 'GET');
        $request->cookies->set(AdminAccess::COOKIE_NAME, $cookie->getValue());

        $this->assertTrue(AdminAccess::hasValidToken($request));
        $this->assertNotSame('test-admin-token-value', $cookie->getValue());
    }

    public function test_logout_invalidates_session_cookie(): void
    {
        $cookie = AdminAccess::makeCookie();
        $this->assertNotNull($cookie);

        $request = Request::create('/api/admin/site-access', 'DELETE');
        $request->cookies->set(AdminAccess::COOKIE_NAME, $cookie->getValue());

        AdminAccess::forgetCookie($request);

        $check = Request::create('/api/admin/stats', 'GET');
        $check->cookies->set(AdminAccess::COOKIE_NAME, $cookie->getValue());
        $this->assertFalse(AdminAccess::hasValidToken($check));
    }

    public function test_matches_master_token(): void
    {
        $this->assertTrue(AdminAccess::matchesMasterToken('test-admin-token-value'));
        $this->assertFalse(AdminAccess::matchesMasterToken('wrong'));
        $this->assertFalse(AdminAccess::matchesMasterToken(''));
    }
}
