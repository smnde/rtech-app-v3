<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $user_owner = User::create([
            'name' => 'Owner',
            'username' => 'owner',
            'password' => Hash::make('owner123'),
        ]);
        $user_owner->assignRole('OWNER');

        $user_usman = User::create([
            'name' => 'Usman',
            'username' => 'envy',
            'password' => Hash::make('123qweasd'),
        ]);
        $user_usman->assignRole('SYSADMIN');

        $user_hekal = User::create([
            'name' => 'Hekal',
            'username' => 'hekal',
            'password' => Hash::make('password123'),
        ]);
        $user_hekal->assignRole('TECH');

        $user_ronni = User::create([
            'name' => 'Ronni',
            'username' => 'ronni',
            'password' => Hash::make('password123'),
        ]);
        $user_ronni->assignRole('TECH');

        $user_rahmat_segedong = User::create([
            'name' => 'Rahmat Segedong',
            'username' => 'rahmat',
            'password' => Hash::make('password123'),
        ]);
        $user_rahmat_segedong->assignRole('ADMIN');

        $user_khalil = User::create([
            'name' => 'Khalil',
            'username' => 'khalil',
            'password' => Hash::make('password123'),
        ]);
        $user_khalil->assignRole('ADMIN');

        $user_joni = User::create([
            'name' => 'Joni',
            'username' => 'joni',
            'password' => Hash::make('password123'),
        ]);
        $user_joni->assignRole('TECH');

        $user_mustafa = User::create([
            'name' => 'Mustafa',
            'username' => 'mustafa',
            'password' => Hash::make('password123'),
        ]);
        $user_mustafa->assignRole('ADMIN');

        // Brand::create(['name' => 'Asus']);
        // Brand::create(['name' => 'Acer']);
        // Brand::create(['name' => 'Apple']);
        // Brand::create(['name' => 'Huawei']);
        // Brand::create(['name' => 'HP']);
        // Brand::create(['name' => 'Dell']);
        // Brand::create(['name' => 'Infinix']);
        // Brand::create(['name' => 'Axioo']);
        // Brand::create(['name' => 'Acvan']);
        // Brand::create(['name' => 'Samsung']);
        // Brand::create(['name' => 'Lenovo']);
        // Brand::create(['name' => 'Zyrex']);
        // Brand::create(['name' => 'Techno']);

        // Category::create(['name' => 'Keyboard']);
        // Category::create(['name' => 'LCD']);
        // Category::create(['name' => 'Baterai']);
        // Category::create(['name' => 'RAM']);
        // Category::create(['name' => 'SSD']);
        // Category::create(['name' => 'Hardisk']);
        // Category::create(['name' => 'Fan']);

        // Supplier::create(['name' => 'Tegra']);
        // Supplier::create(['name' => 'Multi Tech']);
        // Supplier::create(['name' => 'Raja Computer']);
        // Supplier::create(['name' => 'Cipta Store']);
        // Supplier::create(['name' => 'Tiger Computer']);
        // Supplier::create(['name' => 'Mega Global']);
        // Supplier::create(['name' => 'Kuching']);
    }
}
