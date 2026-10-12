<?php

namespace Database\Seeders;

use App\Enums\PlanInterval;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Administrador',
            'email' => 'business.alejandrolopezmurillo@gmail.com',
        ]);

        User::factory()->editor()->create([
            'name' => 'Editor',
            'email' => 'editor@libro.test',
        ]);

        $reader = User::factory()->create([
            'name' => 'Lector de prueba',
            'email' => 'lector@libro.test',
        ]);
        $monthly = Plan::create([
            'name' => 'Mensual',
            'description' => 'Todo el catálogo, renovación cada mes.',
            'interval' => PlanInterval::Monthly,
            'price' => 999,
            'currency' => 'USD',
            'sort_order' => 1,
        ]);
        $yearly = Plan::create([
            'name' => 'Anual',
            'description' => 'Todo el catálogo con dos meses gratis.',
            'interval' => PlanInterval::Yearly,
            'price' => 9990,
            'currency' => 'USD',
            'sort_order' => 2,
        ]);

        $subscription = Subscription::factory()->for($reader)->create(['plan_id' => $yearly->id, 'ends_at' => now()->addYear()]);
        Payment::factory()->paid()->create([
            'user_id' => $reader->id,
            'plan_id' => $yearly->id,
            'subscription_id' => $subscription->id,
            'amount' => $yearly->price,
        ]);

        User::factory()->create([
            'name' => 'Lector sin suscripción',
            'email' => 'sinplan@libro.test',
        ]);

        $this->call([FeaturedBookSeeder::class, CatalogSeeder::class, FinanceDemoSeeder::class, ReadingDemoSeeder::class]);
    }
}
