<?php

namespace Database\Seeders;

use App\Models\PipelineStage;
use Illuminate\Database\Seeder;

class PipelineStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            'Prospect Identified',
            'Contacted',
            'Negotiation',
            'Won',
        ];

        PipelineStage::whereIn('name', ['Qualified', 'Meeting', 'Sample', 'Quote'])
            ->whereDoesntHave('deals')
            ->delete();

        foreach ($stages as $index => $name) {
            PipelineStage::updateOrCreate(
                ['name' => $name],
                [
                    'sort_order' => $index + 1,
                    'is_won' => $name === 'Won',
                    'is_lost' => false,
                ]
            );
        }
    }
}
