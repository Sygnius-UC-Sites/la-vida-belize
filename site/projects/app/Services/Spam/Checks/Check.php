<?php

namespace App\Services\Spam\Checks;

use App\Services\Spam\Result;
use Illuminate\Http\Request;

interface Check
{
    public function run(Request $request, string $form): Result;
}
