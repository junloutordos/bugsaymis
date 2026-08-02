<?php

namespace Tests\Feature\Atlas\Dyna;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DynaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dyna_conversations_and_messages_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('dyna_conversations'));
        $this->assertTrue(Schema::hasColumns('dyna_conversations', ['id', 'user_id', 'title', 'created_at', 'updated_at']));

        $this->assertTrue(Schema::hasTable('dyna_messages'));
        $this->assertTrue(Schema::hasColumns('dyna_messages', [
            'id', 'dyna_conversation_id', 'role', 'content', 'tool_calls', 'created_at',
        ]));
    }
}
