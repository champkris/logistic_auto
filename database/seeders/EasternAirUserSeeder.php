<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class EasternAirUserSeeder extends Seeder
{
    /**
     * Run the database seeds for Eastern Air users.
     */
    public function run(): void
    {
        $users = [
            // IMPORT TEAM - SEA LCB
            [
                'name' => 'SEA LCB Import 1',
                'email' => 'sealcb_import1@easternair.co.th',
            ],
            [
                'name' => 'SEA LCB Import 2',
                'email' => 'sealcb_import2@easternair.co.th',
            ],
            [
                'name' => 'SEA LCB Import 3',
                'email' => 'sealcb_import3@easternair.co.th',
            ],
            [
                'name' => 'SEA LCB Import 4',
                'email' => 'sealcb_import4@easternair.co.th',
            ],

            // STAFF MEMBERS
            [
                'name' => 'Nattapon Bun',
                'email' => 'nattapon.bun@easternair.co.th',
            ],

            // EXPORT TEAM
            [
                'name' => 'Export LCB',
                'email' => 'export_lcb@easternair.co.th',
            ],
            [
                'name' => 'Phonnapha Ro',
                'email' => 'phonnapha.ro@easternair.co.th',
            ],

            // ADMIN USERS
            [
                'name' => 'Peachy (Admin)',
                'email' => 'peachy@easternair.co.th',
            ],
            [
                'name' => 'IT Department (Admin)',
                'email' => 'it@easternair.co.th',
            ],
        ];

        echo "Creating Eastern Air user accounts...\n";

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']], // Find by email
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password123'),
                    'email_verified_at' => now(),
                ]
            );

            if ($user->wasRecentlyCreated) {
                echo "✅ Created: {$userData['name']} ({$userData['email']})\n";
            } else {
                echo "📝 Updated: {$userData['name']} ({$userData['email']})\n";
            }
        }

        echo "\n🎉 Eastern Air user accounts setup complete!\n";
        echo "📧 All accounts use password: password123\n";
    }
}
