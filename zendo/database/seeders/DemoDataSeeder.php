<?php

namespace Database\Seeders;

use App\Modules\Events\Models\Category;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventInstance;
use App\Modules\Events\Models\Teacher;
use App\Modules\Lodging\Models\Building;
use App\Modules\Lodging\Models\Room;
use App\Modules\Meals\Models\DietaryTag;
use App\Modules\Meals\Models\MealPlan;
use App\Modules\Memberships\Models\MembershipPlan;
use App\Modules\People\Models\User;
use App\Modules\People\Models\UserTenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $ivy = Tenant::where('slug', 'ivy')->first();
        $nalanda = Tenant::where('slug', 'nalanda')->first();
        $bodhi = Tenant::where('slug', 'bodhi-tree')->first();

        $this->seedUsersAndRoles($ivy, $nalanda, $bodhi);
        $this->seedCategories();
        $this->seedTeachers();
        $this->seedDietaryTags();

        $this->withTenant($ivy, fn () => $this->seedIvyData($ivy));
        $this->withTenant($nalanda, fn () => $this->seedNalandaData($nalanda));
        $this->withTenant($bodhi, fn () => $this->seedBodhiData($bodhi));
    }

    private function withTenant(Tenant $tenant, callable $callback): void
    {
        app()->instance('current_tenant_id', $tenant->id);
        app()->instance(Tenant::class, $tenant);
        $callback();
    }

    private function seedUsersAndRoles(Tenant $ivy, Tenant $nalanda, Tenant $bodhi): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@zendo.test'],
            ['name' => 'System Admin', 'password' => bcrypt('password'), 'global_role' => 'GLOBAL_ADMIN']
        );

        $alice = User::firstOrCreate(
            ['email' => 'alice@example.com'],
            ['name' => 'Alice Chen', 'password' => bcrypt('password')]
        );

        $bob = User::firstOrCreate(
            ['email' => 'bob@example.com'],
            ['name' => 'Bob van der Berg', 'password' => bcrypt('password')]
        );

        $carol = User::firstOrCreate(
            ['email' => 'carol@example.com'],
            ['name' => 'Carol Williams', 'password' => bcrypt('password')]
        );

        $dave = User::firstOrCreate(
            ['email' => 'dave@example.com'],
            ['name' => 'Dave Jansen', 'password' => bcrypt('password')]
        );

        $somsak = User::firstOrCreate(
            ['email' => 'somsak@example.com'],
            ['name' => 'Somsak Thirapat', 'password' => bcrypt('password')]
        );

        $roles = [
            [$alice, $ivy, 'ADMIN'],
            [$alice, $nalanda, 'VIEWER'],
            [$bob, $ivy, 'EDITOR'],
            [$bob, $nalanda, 'ADMIN'],
            [$carol, $ivy, 'VIEWER'],
            [$dave, $nalanda, 'EDITOR'],
            [$somsak, $bodhi, 'ADMIN'],
            [$somsak, $ivy, 'VIEWER'],
        ];

        foreach ($roles as [$user, $tenant, $role]) {
            UserTenantRole::firstOrCreate(
                ['user_id' => $user->id, 'tenant_id' => $tenant->id],
                ['role' => $role]
            );
        }
    }

    private function seedCategories(): void
    {
        $categories = [
            ['slug' => 'yoga', 'name' => 'Yoga', 'description' => 'Yoga practices and traditions'],
            ['slug' => 'meditation', 'name' => 'Meditation', 'description' => 'Meditation and mindfulness practices'],
            ['slug' => 'wellness', 'name' => 'Wellness', 'description' => 'Holistic wellness and healing'],
            ['slug' => 'creative', 'name' => 'Creative Arts', 'description' => 'Art therapy, writing, and creative expression'],
        ];

        $parents = collect();
        foreach ($categories as $cat) {
            $parents[$cat['slug']] = Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'description' => $cat['description']]
            );
        }

        $children = [
            ['slug' => 'vinyasa', 'name' => 'Vinyasa', 'description' => 'Dynamic flow yoga', 'parent' => 'yoga'],
            ['slug' => 'yin-yoga', 'name' => 'Yin Yoga', 'description' => 'Slow, deep stretching', 'parent' => 'yoga'],
            ['slug' => 'ashtanga', 'name' => 'Ashtanga', 'description' => 'Traditional Ashtanga series', 'parent' => 'yoga'],
            ['slug' => 'vipassana', 'name' => 'Vipassana', 'description' => 'Insight meditation', 'parent' => 'meditation'],
            ['slug' => 'zen', 'name' => 'Zen', 'description' => 'Zen meditation', 'parent' => 'meditation'],
            ['slug' => 'mindfulness', 'name' => 'Mindfulness', 'description' => 'MBSR and mindfulness practices', 'parent' => 'meditation'],
            ['slug' => 'sound-healing', 'name' => 'Sound Healing', 'description' => 'Sound bath and vibrational healing', 'parent' => 'wellness'],
            ['slug' => 'breathwork', 'name' => 'Breathwork', 'description' => 'Conscious breathing practices', 'parent' => 'wellness'],
            ['slug' => 'writing', 'name' => 'Writing', 'description' => 'Creative writing workshops', 'parent' => 'creative'],
            ['slug' => 'art-therapy', 'name' => 'Art Therapy', 'description' => 'Expressive art therapy', 'parent' => 'creative'],
        ];

        foreach ($children as $child) {
            Category::firstOrCreate(
                ['slug' => $child['slug']],
                ['name' => $child['name'], 'description' => $child['description'], 'parent_id' => $parents[$child['parent']]->id]
            );
        }
    }

    private function seedTeachers(): void
    {
        $teachers = [
            ['name' => 'Priya Sharma', 'bio' => 'International yoga teacher with 20 years of experience in Vinyasa and Yin traditions. Trained in Rishikesh, India.', 'specialties' => ['Yoga', 'Vinyasa', 'Yin Yoga'], 'email' => 'priya@zendo.test'],
            ['name' => 'Marcus van Dijk', 'bio' => 'Former Buddhist monk, now teaching Vipassana and mindfulness across Europe.', 'specialties' => ['Meditation', 'Vipassana', 'Mindfulness'], 'email' => 'marcus@zendo.test'],
            ['name' => 'Yuki Tanaka', 'bio' => 'Zen priest and calligraphy artist from Kyoto. Teaches meditation as an art form.', 'specialties' => ['Zen', 'Meditation', 'Calligraphy'], 'email' => 'yuki@zendo.test'],
            ['name' => 'Elena Rossi', 'bio' => 'Sound healer and breathwork facilitator. Combines ancient Tibetan practices with modern neuroscience.', 'specialties' => ['Sound Healing', 'Breathwork'], 'email' => 'elena@zendo.test'],
            ['name' => 'James Okafor', 'bio' => 'Trauma-informed yoga teacher specializing in nervous system regulation.', 'specialties' => ['Trauma-Informed Yoga', 'Somatics'], 'email' => 'james@zendo.test'],
            ['name' => 'Lina Somsak', 'bio' => 'Thai massage therapist and meditation teacher. Runs retreats in Northern Thailand.', 'specialties' => ['Meditation', 'Thai Massage', 'Breathwork'], 'email' => 'lina@zendo.test'],
        ];

        foreach ($teachers as $t) {
            Teacher::firstOrCreate(
                ['email' => $t['email']],
                ['name' => $t['name'], 'bio' => $t['bio'], 'specialties' => $t['specialties']]
            );
        }
    }

    private function seedDietaryTags(): void
    {
        $tags = [
            ['name' => 'Vegetarian', 'slug' => 'vegetarian'],
            ['name' => 'Vegan', 'slug' => 'vegan'],
            ['name' => 'Gluten-Free', 'slug' => 'gluten-free'],
            ['name' => 'Dairy-Free', 'slug' => 'dairy-free'],
            ['name' => 'Nut-Free', 'slug' => 'nut-free'],
            ['name' => 'Halal', 'slug' => 'halal'],
            ['name' => 'Kosher', 'slug' => 'kosher'],
            ['name' => 'Organic', 'slug' => 'organic'],
        ];

        foreach ($tags as $tag) {
            DietaryTag::firstOrCreate(['slug' => $tag['slug']], ['name' => $tag['name']]);
        }
    }

    private function createEvent(Tenant $tenant, array $data, array $teacherEmails = [], array $instanceData = []): Event
    {
        $data['tenant_id'] = $tenant->id;
        $event = Event::create($data);

        foreach ($teacherEmails as $email) {
            $teacher = Teacher::where('email', $email)->first();
            if ($teacher) {
                $event->teachers()->syncWithoutDetaching([$teacher->id]);
            }
        }

        foreach ($instanceData as $instance) {
            EventInstance::create([...$instance, 'event_id' => $event->id]);
        }

        return $event;
    }

    private function seedIvyData(Tenant $ivy): void
    {
        $this->createEvent($ivy, [
            'title' => 'Morning Vinyasa Flow',
            'description' => 'Start your day with an energizing vinyasa flow. Suitable for all levels, this class focuses on breath-synchronized movement to build heat and flexibility.',
            'status' => 'PUBLISHED',
            'starts_at' => now()->addDays(14)->setTime(7, 0),
            'ends_at' => now()->addDays(14)->setTime(8, 30),
            'capacity' => 30,
            'price_cents' => 2500,
            'is_published' => true,
        ], ['priya@zendo.test'], [
            ['title' => 'Week 1 — Foundations', 'starts_at' => now()->addDays(14)->setTime(7, 0), 'ends_at' => now()->addDays(14)->setTime(8, 30), 'capacity' => 30],
            ['title' => 'Week 2 — Flow & Breath', 'starts_at' => now()->addDays(21)->setTime(7, 0), 'ends_at' => now()->addDays(21)->setTime(8, 30), 'capacity' => 30],
            ['title' => 'Week 3 — Peak Poses', 'starts_at' => now()->addDays(28)->setTime(7, 0), 'ends_at' => now()->addDays(28)->setTime(8, 30), 'capacity' => 25],
        ]);

        $this->createEvent($ivy, [
            'title' => 'Silent Retreat Weekend',
            'description' => 'A transformative 3-day silent retreat. Through guided and self-directed meditation, discover the profound stillness that lies beneath the noise. All meals included.',
            'status' => 'PUBLISHED',
            'starts_at' => now()->addMonths(1)->setTime(16, 0),
            'ends_at' => now()->addMonths(1)->addDays(2)->setTime(14, 0),
            'capacity' => 20,
            'price_cents' => 35000,
            'is_published' => true,
        ], ['marcus@zendo.test', 'yuki@zendo.test'], [
            ['title' => 'Spring Silent Retreat', 'starts_at' => now()->addMonths(1)->setTime(16, 0), 'ends_at' => now()->addMonths(1)->addDays(2)->setTime(14, 0), 'capacity' => 20],
            ['title' => 'Summer Silent Retreat', 'starts_at' => now()->addMonths(3)->setTime(16, 0), 'ends_at' => now()->addMonths(3)->addDays(2)->setTime(14, 0), 'capacity' => 24],
        ]);

        $this->createEvent($ivy, [
            'title' => 'Yin Yoga & Sound Healing',
            'description' => 'An evening of deep relaxation combining gentle yin yoga with the healing vibrations of singing bowls and gongs. No experience needed.',
            'status' => 'PUBLISHED',
            'starts_at' => now()->addDays(7)->setTime(19, 0),
            'ends_at' => now()->addDays(7)->setTime(21, 0),
            'capacity' => 40,
            'price_cents' => 3500,
            'is_published' => true,
        ], ['priya@zendo.test']);

        $this->createEvent($ivy, [
            'title' => 'Mindfulness-Based Stress Reduction (MBSR)',
            'description' => 'An 8-week evidence-based program teaching mindfulness meditation and gentle yoga for stress management.',
            'status' => 'PUBLISHED',
            'starts_at' => now()->addDays(10)->setTime(18, 0),
            'ends_at' => now()->addDays(10)->setTime(20, 0),
            'capacity' => 25,
            'price_cents' => 45000,
            'is_published' => true,
        ], ['marcus@zendo.test']);

        $this->createEvent($ivy, [
            'title' => 'Trauma-Informed Yoga Series',
            'description' => 'A 6-week gentle yoga series designed for those healing from trauma. Led by a certified trauma-informed instructor.',
            'status' => 'DRAFT',
            'starts_at' => now()->addMonths(2)->setTime(10, 0),
            'ends_at' => now()->addMonths(2)->setTime(11, 30),
            'capacity' => 12,
            'price_cents' => 18000,
            'is_published' => false,
        ]);

        // Ivy buildings
        $mainHall = Building::create(['tenant_id' => $ivy->id, 'name' => 'Main Hall', 'description' => 'The original stone building with the large meditation hall and kitchen facilities.', 'address' => '12 Chemin des Oliviers, 84220 Gordes, France']);
        $lotusHouse = Building::create(['tenant_id' => $ivy->id, 'name' => 'Lotus House', 'description' => 'A charming farmhouse converted into private suites. Each suite has its own bathroom and small terrace.', 'address' => '14 Chemin des Oliviers, 84220 Gordes, France']);
        $cedarLodge = Building::create(['tenant_id' => $ivy->id, 'name' => 'Cedar Lodge', 'description' => 'A cozy wooden lodge with shared dormitory-style rooms. Perfect for budget-conscious retreatants.', 'address' => '16 Chemin des Oliviers, 84220 Gordes, France']);
        $yogaPavilion = Building::create(['tenant_id' => $ivy->id, 'name' => 'The Yoga Pavilion', 'description' => 'An open-air yoga pavilion with panoramic views of the Luberon valley. Heated in winter.', 'address' => '18 Chemin des Oliviers, 84220 Gordes, France']);

        Room::create(['building_id' => $mainHall->id, 'name' => 'Meditation Hall', 'capacity' => 40, 'room_type' => 'single']);
        Room::create(['building_id' => $mainHall->id, 'name' => 'Library', 'capacity' => 10, 'room_type' => 'single']);
        Room::create(['building_id' => $yogaPavilion->id, 'name' => 'Open Air Studio', 'capacity' => 30, 'room_type' => 'single']);

        for ($i = 1; $i <= 6; $i++) {
            Building::find($lotusHouse->id) && Room::create(['building_id' => $lotusHouse->id, 'name' => "Suite {$i}", 'capacity' => 2, 'room_type' => 'double']);
        }
        for ($i = 1; $i <= 4; $i++) {
            Room::create(['building_id' => $cedarLodge->id, 'name' => "Dorm {$i}", 'capacity' => 6, 'room_type' => 'dormitory']);
        }

        // Ivy meal plans
        MealPlan::create(['tenant_id' => $ivy->id, 'name' => 'Full Board — French Country', 'description' => 'Three meals daily featuring local Provencal cuisine. Fresh breads, seasonal vegetables, and local cheeses.', 'price_cents' => 4500, 'is_available' => true]);
        MealPlan::create(['tenant_id' => $ivy->id, 'name' => 'Half Board', 'description' => 'Breakfast and dinner daily. Light lunch available for purchase.', 'price_cents' => 3000, 'is_available' => true]);
        MealPlan::create(['tenant_id' => $ivy->id, 'name' => 'Vegetarian Full Board', 'description' => 'Three vegetarian meals daily, with vegan options always available. Organic produce from our garden.', 'price_cents' => 5000, 'is_available' => true]);

        // Ivy memberships
        MembershipPlan::create(['tenant_id' => $ivy->id, 'name' => 'Community Member', 'description' => 'Access to all open events, 10% off retreats, and monthly newsletter.', 'price_cents' => 2500, 'billing_cycle' => 'monthly', 'is_active' => true]);
        MembershipPlan::create(['tenant_id' => $ivy->id, 'name' => 'Patron', 'description' => 'All Community Member benefits plus priority booking, private sessions, and annual retreat discount.', 'price_cents' => 7500, 'billing_cycle' => 'monthly', 'is_active' => true]);
        MembershipPlan::create(['tenant_id' => $ivy->id, 'name' => 'Founding Circle', 'description' => 'Lifetime membership for founding supporters. Includes everything in Patron plus exclusive retreats and the annual Founders Dinner.', 'price_cents' => 500000, 'billing_cycle' => 'yearly', 'is_active' => true]);
    }

    private function seedNalandaData(Tenant $nalanda): void
    {
        $this->createEvent($nalanda, [
            'title' => 'Philosophy of Mind',
            'description' => 'Explore Buddhist philosophy through Socratic dialogue and guided meditation. No prior experience needed — just curiosity and an open mind.',
            'status' => 'PUBLISHED',
            'starts_at' => now()->addDays(5)->setTime(19, 0),
            'ends_at' => now()->addDays(5)->setTime(21, 30),
            'capacity' => 50,
            'price_cents' => 1500,
            'is_published' => true,
        ], ['marcus@zendo.test']);

        $this->createEvent($nalanda, [
            'title' => 'Zen Meditation Intensive',
            'description' => 'A full-day zen meditation intensive with periods of sitting and walking meditation, dharma talks, and individual interviews with the teacher.',
            'status' => 'PUBLISHED',
            'starts_at' => now()->addDays(20)->setTime(9, 0),
            'ends_at' => now()->addDays(20)->setTime(17, 0),
            'capacity' => 30,
            'price_cents' => 8000,
            'is_published' => true,
        ], ['yuki@zendo.test']);

        $this->createEvent($nalanda, [
            'title' => 'Mindful Leadership Workshop',
            'description' => 'A weekend workshop integrating mindfulness practices with leadership development. For managers, executives, and anyone seeking to lead with awareness.',
            'status' => 'PUBLISHED',
            'starts_at' => now()->addMonths(2)->setTime(10, 0),
            'ends_at' => now()->addMonths(2)->addDays(1)->setTime(17, 0),
            'capacity' => 24,
            'price_cents' => 12000,
            'is_published' => true,
        ], ['marcus@zendo.test']);

        // Nalanda buildings
        $mainBuilding = Building::create(['tenant_id' => $nalanda->id, 'name' => 'Nalanda House', 'description' => 'A converted canal house with meditation rooms, a library, and modern amenities in the center of Amsterdam.', 'address' => 'Herengracht 426, 1016 BD Amsterdam, Netherlands']);

        Room::create(['building_id' => $mainBuilding->id, 'name' => 'Zendo', 'capacity' => 30, 'room_type' => 'single']);
        Room::create(['building_id' => $mainBuilding->id, 'name' => 'Library', 'capacity' => 8, 'room_type' => 'single']);
        for ($i = 1; $i <= 4; $i++) {
            Room::create(['building_id' => $mainBuilding->id, 'name' => "Room {$i}A", 'capacity' => 2, 'room_type' => 'double']);
        }

        // Nalanda memberships (no meals for Nalanda!)
        MembershipPlan::create(['tenant_id' => $nalanda->id, 'name' => 'Friend of Nalanda', 'description' => 'Access to all weekly sits, 15% off workshops, and borrowing privileges from our library.', 'price_cents' => 1500, 'billing_cycle' => 'monthly', 'is_active' => true]);
        MembershipPlan::create(['tenant_id' => $nalanda->id, 'name' => 'Nalanda Sustainer', 'description' => 'All Friend benefits plus free workshop admission and priority registration for intensives.', 'price_cents' => 5000, 'billing_cycle' => 'monthly', 'is_active' => true]);
    }

    private function seedBodhiData(Tenant $bodhi): void
    {
        $this->createEvent($bodhi, [
            'title' => 'Sound Healing & Breathwork Journey',
            'description' => 'A profound 2-hour journey through sound and breath. Using gongs, singing bowls, and pranayama techniques, you will release stored tension and access deep states of relaxation.',
            'status' => 'PUBLISHED',
            'starts_at' => now()->addDays(3)->setTime(18, 0),
            'ends_at' => now()->addDays(3)->setTime(20, 0),
            'capacity' => 25,
            'price_cents' => 2000,
            'is_published' => true,
        ], ['elena@zendo.test']);

        $this->createEvent($bodhi, [
            'title' => 'Thai Cooking & Meditation Retreat',
            'description' => 'Three days of meditation, Thai cooking classes, and cultural immersion. Learn to cook authentic Thai dishes while cultivating mindfulness in every moment.',
            'status' => 'PUBLISHED',
            'starts_at' => now()->addMonths(1)->setTime(14, 0),
            'ends_at' => now()->addMonths(1)->addDays(2)->setTime(12, 0),
            'capacity' => 15,
            'price_cents' => 18000,
            'is_published' => true,
        ], ['lina@zendo.test']);

        // Bodhi buildings
        $bamboo = Building::create(['tenant_id' => $bodhi->id, 'name' => 'Bamboo House', 'description' => 'A traditional Thai teak house raised on stilts, surrounded by tropical gardens.', 'address' => '88/5 Moo 3, Tambon Tha Suea, Chiang Mai 50200, Thailand']);

        Room::create(['building_id' => $bamboo->id, 'name' => 'Upper Deck', 'capacity' => 8, 'room_type' => 'dormitory']);
        for ($i = 1; $i <= 3; $i++) {
            Room::create(['building_id' => $bamboo->id, 'name' => "Garden Room {$i}", 'capacity' => 2, 'room_type' => 'double']);
        }

        // Bodhi meal plans (no lodging or memberships for Bodhi)
        MealPlan::create(['tenant_id' => $bodhi->id, 'name' => 'Thai Full Board', 'description' => 'Three authentic Thai meals daily. Fresh curries, som tam, and pad thai. Vegetarian options always available.', 'price_cents' => 2500, 'is_available' => true]);
        MealPlan::create(['tenant_id' => $bodhi->id, 'name' => 'Detox Cleanse', 'description' => 'Fresh juices, smoothies, and raw food meals designed for deep cleansing. Includes daily herbal tea service.', 'price_cents' => 3500, 'is_available' => true]);
    }
}
