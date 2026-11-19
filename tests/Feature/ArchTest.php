<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

arch('no debugging calls are left in the code')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('App\Contracts')
    ->toBeInterfaces();

arch('models extend Eloquent')
    ->expect('App\Models')
    ->toExtend(Model::class)
    ->ignoring('App\Models\Concerns');

arch('services use strict types')
    ->expect('App\Services')
    ->toUseStrictTypes();

arch('exam and payment services do not depend on the web layer')
    ->expect('App\Services')
    ->not->toUse([Request::class, 'Livewire']);
