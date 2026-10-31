<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+226'.fake()->unique()->numerify('7#######'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Client,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function provider(): static
    {
        return $this->state(fn () => ['role' => UserRole::Provider]);
    }

    /** Administrateur ; super-administrateur par défaut, ou le rôle donné (slug). */
    public function admin(string $role = Role::SUPER_ADMIN): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin,
            'admin_role_id' => Role::where('slug', $role)->value('id'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
