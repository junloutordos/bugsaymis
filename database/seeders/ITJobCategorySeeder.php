<?php

namespace Database\Seeders;

use App\Models\ITJobCategory;
use Illuminate\Database\Seeder;

class ITJobCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            "Hardware Repair",
            "Hardware Installation",
            "Preventive Maintenance",
            "Software Modification",
            "Software Installation",
            "Software Development",
            "Network Connection",
            "Account Access",
            "Technical Assistance on Events",
            "Graphic Design",
            "Video Editing/Production",
            "Posting to Website",
            "Posting to Social Media",
            "Poll Survey Creation",
            "Online Meeting Request",
            "DTR Generation",
            "DTR System Concerns",
            "CCTV Footage Review",
            "CCTV Footage Retrieval",
            "SIMS Concerns",
            "Document Tracking Concerns",
            "eNGAS Concerns",
            "Other",
        ];

        foreach ($categories as $cat) {
            ITJobCategory::firstOrCreate(['name' => $cat]);
        }
    }
}

