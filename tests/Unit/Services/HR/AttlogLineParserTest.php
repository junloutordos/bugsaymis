<?php

namespace Tests\Unit\Services\HR;

use App\Services\HR\AttlogLineParser;
use Tests\TestCase;

class AttlogLineParserTest extends TestCase
{
    private AttlogLineParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AttlogLineParser();
    }

    public function test_four_field_check_in_line_resolves_to_time_in(): void
    {
        $result = $this->parser->parseText("101\t2026-07-23 07:58:03\t1\t0");

        $this->assertSame(0, $result['skipped']);
        $this->assertSame([
            ['device_employee_id' => '101', 'log_datetime' => '2026-07-23 07:58:03', 'log_type' => 'time_in'],
        ], $result['rows']);
    }

    public function test_four_field_check_out_line_resolves_to_time_out(): void
    {
        $result = $this->parser->parseText("101\t2026-07-23 17:04:31\t1\t1");

        $this->assertSame('time_out', $result['rows'][0]['log_type']);
    }

    public function test_two_field_line_defaults_to_time_in(): void
    {
        $result = $this->parser->parseText("101\t2026-07-23 07:58:03");

        $this->assertSame(0, $result['skipped']);
        $this->assertSame('time_in', $result['rows'][0]['log_type']);
    }

    public function test_slash_delimited_date_is_normalized(): void
    {
        $result = $this->parser->parseText("101\t2026/07/23 07:58:03\t1\t0");

        $this->assertSame('2026-07-23 07:58:03', $result['rows'][0]['log_datetime']);
    }

    public function test_letter_check_type_codes_are_mapped(): void
    {
        $in     = $this->parser->parseText("101\t2026-07-23 07:58:03\t1\tI");
        $out    = $this->parser->parseText("101\t2026-07-23 17:00:00\t1\tO");
        $breakOut = $this->parser->parseText("101\t2026-07-23 12:00:00\t1\tOO");
        $breakIn  = $this->parser->parseText("101\t2026-07-23 13:00:00\t1\tOI");

        $this->assertSame('time_in', $in['rows'][0]['log_type']);
        $this->assertSame('time_out', $out['rows'][0]['log_type']);
        $this->assertSame('time_out', $breakOut['rows'][0]['log_type']);
        $this->assertSame('time_in', $breakIn['rows'][0]['log_type']);
    }

    public function test_header_lines_are_ignored_without_counting_as_skipped(): void
    {
        $result = $this->parser->parseText("PIN\tName\tDate/Time\n101\t2026-07-23 07:58:03\t1\t0");

        $this->assertSame(0, $result['skipped']);
        $this->assertCount(1, $result['rows']);
    }

    public function test_blank_lines_are_ignored_without_counting_as_skipped(): void
    {
        $result = $this->parser->parseText("101\t2026-07-23 07:58:03\t1\t0\n\n\n");

        $this->assertSame(0, $result['skipped']);
        $this->assertCount(1, $result['rows']);
    }

    public function test_leading_bom_is_stripped(): void
    {
        $result = $this->parser->parseText("\xEF\xBB\xBF101\t2026-07-23 07:58:03\t1\t0");

        $this->assertSame('101', $result['rows'][0]['device_employee_id']);
    }

    public function test_unparseable_datetime_is_counted_as_skipped(): void
    {
        $result = $this->parser->parseText("101\tnot-a-date\t1\t0");

        $this->assertSame(1, $result['skipped']);
        $this->assertSame([], $result['rows']);
    }

    public function test_multiline_text_aggregates_rows_and_skipped_correctly(): void
    {
        $text = implode("\n", [
            'PIN	Name	Date/Time',
            '101	2026-07-23 07:58:03	1	0',
            '',
            '102	garbage	1	0',
            '103	2026-07-23 08:01:15	1	1',
        ]);

        $result = $this->parser->parseText($text);

        $this->assertSame(1, $result['skipped']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame('101', $result['rows'][0]['device_employee_id']);
        $this->assertSame('103', $result['rows'][1]['device_employee_id']);
    }
}
