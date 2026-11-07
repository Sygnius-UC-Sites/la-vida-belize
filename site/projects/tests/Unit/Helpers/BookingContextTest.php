<?php

namespace Tests\Unit\Helpers;

use App\Helpers\BookingContext;
use Illuminate\Http\Request;
use Tests\TestCase;

class BookingContextTest extends TestCase
{
    public function test_default_values_when_no_params(): void
    {
        $req = Request::create('/anything');
        $ctx = BookingContext::fromRequest($req);
        $this->assertNull($ctx->check_in);
        $this->assertNull($ctx->check_out);
        $this->assertSame(1, $ctx->adults);
        $this->assertSame(0, $ctx->children);
        $this->assertSame(0, $ctx->pets);
        $this->assertNull($ctx->type);
    }

    public function test_reads_full_param_set(): void
    {
        $req = Request::create('/anything?check_in=2026-06-01&check_out=2026-06-05&adults=4&children=2&pets=1&type=condo&bedrooms=3&min_price=100&max_price=500');
        $ctx = BookingContext::fromRequest($req);
        $this->assertSame('2026-06-01', $ctx->check_in);
        $this->assertSame('2026-06-05', $ctx->check_out);
        $this->assertSame(4, $ctx->adults);
        $this->assertSame(2, $ctx->children);
        $this->assertSame(1, $ctx->pets);
        $this->assertSame('condo', $ctx->type);
        $this->assertSame(3, $ctx->bedrooms);
        $this->assertSame(100, $ctx->min_price);
        $this->assertSame(500, $ctx->max_price);
    }

    public function test_forwardable_omits_defaults(): void
    {
        $req = Request::create('/?adults=1&children=0');
        $ctx = BookingContext::fromRequest($req);
        $this->assertSame([], $ctx->forwardable());
    }

    public function test_forwardable_includes_non_defaults(): void
    {
        $req = Request::create('/?check_in=2026-06-01&adults=3');
        $ctx = BookingContext::fromRequest($req);
        $fwd = $ctx->forwardable();
        $this->assertSame('2026-06-01', $fwd['check_in']);
        $this->assertSame(3, $fwd['adults']);
    }

    public function test_invalid_type_becomes_null(): void
    {
        $req = Request::create('/?type=junk');
        $ctx = BookingContext::fromRequest($req);
        $this->assertNull($ctx->type);
    }
}
