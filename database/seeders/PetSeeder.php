<?php

namespace Database\Seeders;

use App\Models\Pet;
use App\Models\PetBehaviour;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PetSeeder extends Seeder
{
    use WithoutModelEvents;

    private array $behaviors = [
        'Friendly', 'Playful', 'Energetic', 'Calm', 'Affectionate', 'Independent', 'Social', 'Curious', 'Lazy', 'Aggressive', 'Protective', 'Gentle', 'Active', 'Quiet', 'Vocal', 'Loyal', 'Friendly with kids', 'Good with other pets', 'Trainable', 'Smart'
    ];

    public function run(): void
    {
        // Get or create admin user for created_by
        $admin = User::where('role', 'admin')->first();

        if (!$admin) {
            echo "Warning: No admin user found. Create admin first using AdminSeeder.\n";
            return;
        }

        // Create 100 pets with behaviors
        Pet::factory(100)
            ->sequence(fn () => ['created_by' => $admin->id])
            ->create()
            ->each(function (Pet $pet) {
                // Get random behaviors (2-5 per pet)
                $count = rand(2, 5);
                $behaviors = $this->behaviors;
                shuffle($behaviors);
                $randomBehaviors = array_slice($behaviors, 0, $count);

                // Create behavior records
                foreach ($randomBehaviors as $behavior) {
                    PetBehaviour::create([
                        'pet_id' => $pet->id,
                        'behaviour' => $behavior,
                    ]);
                }

                // Sync geo point for PostGIS
                $pet->syncGeoPoint();

                echo "✓ Created pet: {$pet->name} ({$pet->species} - {$pet->breed})\n";
            });

        echo "\n✅ Successfully seeded 100 pets with behaviors and geolocation!\n";
    }
}
