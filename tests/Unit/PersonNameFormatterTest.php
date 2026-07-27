<?php

namespace Tests\Unit;

use App\Models\Pds;
use App\Models\PDSPersonalInfo;
use App\Models\User;
use App\Services\PersonNameFormatter;
use Tests\TestCase;

class PersonNameFormatterTest extends TestCase
{
    public function test_it_uses_the_pds_first_name_for_greetings(): void
    {
        $personalInfo = new PDSPersonalInfo(['first_name' => 'Juan Carlos', 'surname' => 'Dela Cruz']);
        $pds = new Pds;
        $pds->setRelation('personalInfo', $personalInfo);

        $user = new User(['name' => 'Dela Cruz, Juan Carlos S.']);
        $user->setRelation('pds', $pds);

        $this->assertSame('Juan Carlos', (new PersonNameFormatter)->givenName($user));
    }

    public function test_it_extracts_the_first_name_from_last_name_first_display_names(): void
    {
        $user = new User(['name' => 'Fernando, Michelle B.']);
        $user->setRelation('pds', null);

        $this->assertSame('Michelle', (new PersonNameFormatter)->givenName($user));
    }

    public function test_it_extracts_the_first_name_from_first_name_last_display_names(): void
    {
        $user = new User(['name' => 'Juan Santos DelaCruz']);
        $user->setRelation('pds', null);

        $this->assertSame('Juan', (new PersonNameFormatter)->givenName($user));
    }

    public function test_it_formats_last_first_middle_display_names(): void
    {
        $user = new User(['name' => 'Fernando, Michelle B.']);
        $user->setRelation('pds', null);

        $this->assertSame('MICHELLE B. FERNANDO', (new PersonNameFormatter)->formal($user));
    }

    public function test_it_formats_first_middle_last_display_names(): void
    {
        $user = new User(['name' => 'Juan Santos DelaCruz']);
        $user->setRelation('pds', null);

        $this->assertSame('JUAN S. DELACRUZ', (new PersonNameFormatter)->formal($user));
    }

    public function test_it_strips_na_placeholder_suffix_from_pds_records(): void
    {
        $personalInfo = new PDSPersonalInfo([
            'first_name' => 'Divine Faith', 'middle_name' => 'Gemida',
            'surname' => 'Almocera', 'name_ext' => 'N/A',
        ]);
        $pds = new Pds;
        $pds->setRelation('personalInfo', $personalInfo);

        $user = new User(['name' => 'Almocera, Divine Faith G.']);
        $user->setRelation('pds', $pds);

        $this->assertSame('DIVINE FAITH G. ALMOCERA', (new PersonNameFormatter)->formal($user));
    }

    public function test_it_strips_none_placeholder_middle_name_from_pds_records(): void
    {
        $personalInfo = new PDSPersonalInfo([
            'first_name' => 'Juan', 'middle_name' => 'None',
            'surname' => 'Dela Cruz', 'name_ext' => null,
        ]);
        $pds = new Pds;
        $pds->setRelation('personalInfo', $personalInfo);

        $user = new User(['name' => 'Dela Cruz, Juan']);
        $user->setRelation('pds', $pds);

        $this->assertSame('JUAN DELA CRUZ', (new PersonNameFormatter)->formal($user));
    }
}
