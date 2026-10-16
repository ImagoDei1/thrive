<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_values', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            // One of ReferenceValue::TYPES, e.g. 'channel_category'.
            $table->string('type', 50);
            // Stored on the referencing rows (channels.category etc.) — immutable once created.
            $table->string('code', 50);
            $table->string('label', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // Codes the application branches on (e.g. organisation_type 'individual') — cannot be deleted or hidden.
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['type', 'code']);
            $table->index(['type', 'is_active', 'sort_order']);
        });

        // Seed with the values that were previously hardcoded as model constants.
        $defaults = [
            'channel_category' => [
                'church'      => 'Church / Worship',
                'education'   => 'Education',
                'news'        => 'News & Current Affairs',
                'music'       => 'Music',
                'sports'      => 'Sports',
                'children'    => 'Children',
                'documentary' => 'Documentary',
                'general'     => 'General Entertainment',
            ],
            'channel_language' => [
                'en' => 'English',
                'ny' => 'Chichewa',
                'tu' => 'Tumbuka',
                'yo' => 'Yao',
                'sw' => 'Swahili',
                'fr' => 'French',
            ],
            'organisation_type' => [
                'church'      => 'Church / Ministry',
                'broadcaster' => 'Broadcaster / Media house',
                'ngo'         => 'NGO / Non-profit',
                'school'      => 'School / Education',
                'business'    => 'Registered business',
                'individual'  => 'Individual creator',
            ],
            'id_type' => [
                'national_id'     => 'National ID',
                'passport'        => 'Passport',
                'drivers_licence' => "Driver's licence",
            ],
        ];

        $system = ['organisation_type' => ['individual']];

        $rows = [];
        foreach ($defaults as $type => $values) {
            $order = 0;
            foreach ($values as $code => $label) {
                $rows[] = [
                    'type'       => $type,
                    'code'       => $code,
                    'label'      => $label,
                    'sort_order' => $order += 10,
                    'is_active'  => true,
                    'is_system'  => in_array($code, $system[$type] ?? [], true),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('reference_values')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_values');
    }
};
