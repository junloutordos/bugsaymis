<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\PersonNameFormatter;
use Tests\TestCase;

class PersonNameFormatterTest extends TestCase
{
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
}
