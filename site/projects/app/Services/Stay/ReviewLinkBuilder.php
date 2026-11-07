<?php

namespace App\Services\Stay;

final class ReviewLinkBuilder
{
    public static function build(string $token): string
    {
        return url('/review/' . $token);
    }
}
