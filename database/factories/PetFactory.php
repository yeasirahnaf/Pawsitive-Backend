<?php

namespace Database\Factories;

use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pet>
 */
class PetFactory extends Factory
{
    protected $model = Pet::class;

    private static array $petData = [
        'Dogs' => [
            'breeds' => ['Labrador Retriever', 'German Shepherd', 'Golden Retriever', 'French Bulldog', 'Bulldog', 'Poodle', 'Beagle', 'Dachshund', 'Husky', 'Corgi', 'Boxer', 'Rottweiler', 'Shiba Inu', 'Maltese', 'Pomeranian', 'Chihuahua', 'Great Dane', 'Cocker Spaniel', 'English Springer Spaniel', 'St. Bernard'],
            'colors' => ['Black', 'White', 'Brown', 'Golden', 'Red', 'Cream', 'Gray', 'Black and White', 'Brown and White', 'Fawn', 'Brindle', 'Parti-color'],
            'sizes' => ['small', 'medium', 'large', 'extra_large'],
            'prices' => [500, 800, 1200, 1500, 2000, 2500, 3000, 3500, 4000, 5000],
        ],
        'Cats' => [
            'breeds' => ['Persian', 'Maine Coon', 'British Shorthair', 'Siamese', 'Bengal', 'Ragdoll', 'Scottish Fold', 'Sphynx', 'Abyssinian', 'Birman', 'Bombay', 'Burmese', 'Cornish Rex', 'Devon Rex', 'Japanese Bobtail', 'Manx', 'Norwegian Forest Cat', 'Ocicat', 'Russian Blue', 'Turkish Angora'],
            'colors' => ['Black', 'White', 'Orange', 'Gray', 'Cream', 'Brown', 'Blue', 'Burgundy', 'Chocolate', 'Lilac', 'Seal-point', 'Striped'],
            'sizes' => ['small', 'medium', 'large'],
            'prices' => [300, 600, 1000, 1500, 2000, 2500, 3000],
        ],
        'Rabbits' => [
            'breeds' => ['Holland Lop', 'Dwarf Hotot', 'Mini Lop', 'Lionhead', 'Rex', 'Flemish Giant', 'New Zealand', 'Californian', 'English Angora', 'French Angora'],
            'colors' => ['White', 'Black', 'Brown', 'Gray', 'Orange', 'Spotted', 'Agouti'],
            'sizes' => ['small', 'medium', 'large'],
            'prices' => [150, 250, 400, 600, 800],
        ],
        'Birds' => [
            'breeds' => ['Parrot', 'Cockatiel', 'Budgie', 'Canary', 'Finch', 'Macaw', 'Conure', 'Lovebird', 'Mynah', 'Crow'],
            'colors' => ['Green', 'Yellow', 'Red', 'Blue', 'White', 'Gray', 'Orange', 'Multi-color'],
            'sizes' => ['small', 'medium', 'large'],
            'prices' => [100, 200, 400, 800, 1200, 2000],
        ],
        'Fish' => [
            'breeds' => ['Goldfish', 'Betta', 'Guppy', 'Tetra', 'Cichlid', 'Angelfish', 'Catfish', 'Loach', 'Discus', 'Koi'],
            'colors' => ['Red', 'Orange', 'White', 'Black', 'Blue', 'Yellow', 'Multi-color', 'Metallic'],
            'sizes' => ['small', 'medium', 'large'],
            'prices' => [30, 50, 100, 150, 250, 400],
        ],
    ];

    private static array $behaviors = [
        'Friendly', 'Playful', 'Energetic', 'Calm', 'Affectionate', 'Independent', 'Social', 'Curious', 'Lazy', 'Aggressive', 'Protective', 'Gentle', 'Active', 'Quiet', 'Vocal', 'Loyal', 'Friendly with kids', 'Good with other pets', 'Trainable', 'Smart'
    ];

    private static array $locations = [
        ['name' => 'Downtown', 'lat' => 24.8607, 'lng' => 67.0011],
        ['name' => 'Clifton', 'lat' => 24.7679, 'lng' => 67.0179],
        ['name' => 'Defence', 'lat' => 24.8077, 'lng' => 67.0521],
        ['name' => 'Gulshan', 'lat' => 24.8140, 'lng' => 67.0719],
        ['name' => 'Bandra', 'lat' => 24.8473, 'lng' => 67.0628],
        ['name' => 'North Karachi', 'lat' => 24.9267, 'lng' => 67.0833],
        ['name' => 'Saddar', 'lat' => 24.8476, 'lng' => 67.0066],
        ['name' => 'Korangi', 'lat' => 24.7761, 'lng' => 67.3014],
        ['name' => 'Malir', 'lat' => 24.8556, 'lng' => 67.2283],
        ['name' => 'Lyari', 'lat' => 24.8372, 'lng' => 67.0221],
    ];

    public function definition(): array
    {
        $species = array_rand(self::$petData);
        $petInfo = self::$petData[$species];

        $location = $this->faker->randomElement(self::$locations);

        return [
            'name' => $this->faker->word() . ' ' . $this->faker->firstName(),
            'species' => $species,
            'breed' => $this->faker->randomElement($petInfo['breeds']),
            'age_months' => $this->faker->numberBetween(1, 120),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'size' => $this->faker->randomElement($petInfo['sizes']),
            'color' => $this->faker->randomElement($petInfo['colors']),
            'price' => (float) $this->faker->randomElement($petInfo['prices']),
            'health_records' => json_encode([
                'vaccinated' => $this->faker->boolean,
                'neutered' => $this->faker->boolean,
                'last_checkup' => $this->faker->dateTimeThisYear()->format('Y-m-d'),
            ]),
            'description' => $this->faker->sentence(6),
            'status' => $this->faker->randomElement(['available', 'reserved', 'sold']),
            'latitude' => (float) $location['lat'] + ($this->faker->randomFloat(4, -0.01, 0.01)),
            'longitude' => (float) $location['lng'] + ($this->faker->randomFloat(4, -0.01, 0.01)),
            'location_name' => $location['name'],
            'created_by' => null, // Will be set to admin user in seeder
        ];
    }

    /**
     * Generate behaviors for the pet (random selection from behavior list)
     */
    public function withBehaviors(): array
    {
        return array_slice(
            array_shuffle(self::$behaviors),
            0,
            $this->faker->numberBetween(2, 5)
        );
    }
}
