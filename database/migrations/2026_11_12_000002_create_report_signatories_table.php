<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_signatories', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('position')->unique();
            $table->string('label', 60);
            $table->string('name', 120);
            $table->text('title');
            $table->timestamps();
        });

        $rows = [
            ['Prepared By:', 'FRANCIS EDUARD T. MALITIG, LPT', 'Administrative Assistant III'],
            ['Checked By:', 'ANNA RHEA C. OPEÑA, MMPA', 'HRDU Officer'],
            ['Attested by:', 'DR. KEVIN V. ANGAY, LPT', 'Vice President for Administration and Finance'],
            ['Certified true and correct:', 'DR. RONALD A. GONZALES, LPT', "College President\nCity College of Calamba"],
        ];

        foreach ($rows as $i => [$label, $name, $title]) {
            DB::table('report_signatories')->insert([
                'position' => $i + 1, 'label' => $label, 'name' => $name, 'title' => $title,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_signatories');
    }
};
