<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Traits\HasRoles;

it('usa el trait de roles de spatie', function (): void {
    expect(class_uses_recursive(User::class))->toContain(HasRoles::class);
});
