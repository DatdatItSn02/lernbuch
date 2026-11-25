<?php

arch('không để lại hàm gỡ lỗi')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('model nằm trong App\Models hoặc module')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');
