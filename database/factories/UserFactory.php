<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** The project doesn't ship Faker, so uniqueness comes from a counter. */
    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $n = ++self::$sequence;

        return [
            'name' => 'Test User '.$n,
            'email' => 'user'.$n.'@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }
}
