<?php

namespace Tests\ValidateRegister\Unit;

use Icmbio\ValidateXpathExpression\Xpath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ValidateTest extends TestCase
{
    #[Test]
    public function it_validates_number_ranges()
    {
        $this->assertTrue(Xpath::validate('. >= 1 and . <= 100', 10));
        $this->assertFalse(Xpath::validate('. >= 1 and . <= 100', 150));
    }

    #[Test]
    public function it_validates_boolean_inversion()
    {
        $this->assertTrue(Xpath::validate('not(. >= 1 and . <= 100)', -10));
    }

    #[Test]
    public function it_validates_floor_function()
    {
        $this->assertTrue(Xpath::validate('floor(.) = 4', 4.7));
        $this->assertTrue(Xpath::validate('floor(.) = 5', 5.7));
        $this->assertFalse(Xpath::validate('floor(.) = 5', 4.7));
    }

    #[Test]
    public function it_ceiling_floor_function()
    {
        $this->assertTrue(Xpath::validate('ceiling(.) = 5', 4.3));
        $this->assertTrue(Xpath::validate('ceiling(.) = 7', 6.2));
    }

    #[Test]
    public function it_validates_string_length()
    {
        $this->assertTrue(Xpath::validate('string-length(.) = 7', "abacate"));
        $this->assertFalse(Xpath::validate('string-length(.) = 5', "abacate"));
    }

    #[Test]
    public function it_supports_variable_interpolation()
    {
        $this->assertTrue(Xpath::validate('. >= ${min} and . <= ${max}', 10, ["min" => 1, "max" => 100]));
        $this->assertFalse(Xpath::validate('. >= ${min} and . <= ${max}', 0, ["min" => 1, "max" => 100]));
    }

    #[Test]
    public function it_returns_non_boolean_when_requested()
    {
        $this->assertSame(7, Xpath::validate('string-length(.)', "abacate", [], false));
        $this->assertTrue(Xpath::validate('string_length(.) = 11', "40258997853"));
        $this->assertTrue(Xpath::validate('string_length(.) = 7', "abacate"));
        $this->assertTrue(Xpath::validate('string-length(.) = 15', "632587415222360"));
    }

    #[Test]
    public function it_validates_number_functions()
    {
        $this->assertTrue(Xpath::validate('number(.) = 3.2', 3.2));
        $this->assertTrue(Xpath::validate('number(string(.)) = 6', 6));
    }

    #[Test]
    public function it_validates_choose_function()
    {
        $this->assertTrue(Xpath::validate('choose(true(), 1, 2) = 1', null));
        $this->assertTrue(Xpath::validate('choose(false(), 1, 2) = 2', null));
    }

    #[Test]
    public function it_validates_int_function()
    {
        $this->assertTrue(Xpath::validate('int(.) = 4', 4.7));
    }

    #[Test]
    public function it_validates_contains_function()
    {
        $this->assertTrue(Xpath::validate('contains("abc", .)', "b"));
        $this->assertFalse(Xpath::validate('contains("abc", .)', "e"));
    }

    #[Test]
    public function it_validates_comparison_operators()
    {
        $this->assertTrue(Xpath::validate('5 < .', 10));
        $this->assertFalse(Xpath::validate('5 > .', 10));
        $this->assertFalse(Xpath::validate('5 != .', 5));
        $this->assertTrue(Xpath::validate('5 != .', 10));
        $this->assertTrue(Xpath::validate('5 + 5 = .', 10));
        $this->assertTrue(Xpath::validate('5 - 51 = .', -46));
    }

    #[Test]
    public function it_validates_boolean_expressions()
    {
        $this->assertTrue(Xpath::validate('true() or false()', null));
        $this->assertFalse(Xpath::validate('false() or false()', null));
    }

    #[Test]
    public function it_validates_arithmetic_expressions()
    {
        $this->assertTrue(Xpath::validate('(. div 5) = 2', 10));
        $this->assertTrue(Xpath::validate('(. * 5) = 50', 10));
        $this->assertTrue(Xpath::validate('(. div 5) < .', 10));
        $this->assertTrue(Xpath::validate('(. * 5) > .', 10));
        $this->assertEquals(-2.0, Xpath::validate('(. div -5)', 10, [], false));
    }

    #[Test]
    public function it_validates_mod_function()
    {
        $this->assertTrue(Xpath::validate('(. mod 2) = 0', 10));
        $this->assertTrue(Xpath::validate('(. mod 2) = 1', 11));
        $this->assertFalse(Xpath::validate('(. mod 2) = 1', 10));
        $this->assertFalse(Xpath::validate('(. mod 2) = 0', 11));
    }

    #[Test]
    public function it_validates_range()
    {
        $this->assertTrue(Xpath::validate('. >= ${min} and . <= ${max}', 10, ["max" => 100, "min" => 10]));
        $this->assertFalse(Xpath::validate('. >= ${min} and . <= ${max}', 10, ["max" => 100, "min" => 20]));
        $this->assertFalse(Xpath::validate('${min} = "" and ${max} = ""', null, ["max" => 100, "min" => 20]));
    }

    #[Test]
    public function it_validates_string_functions()
    {
        $this->assertTrue(Xpath::validate('substring-after("aa&bb", ${sep}) = "bb"', "&", ['sep' => '&']));
        $this->assertTrue(Xpath::validate('substring-before("aa&bb", ${sep}) = "aa"', "&", ['sep' => '&']));
        $this->assertTrue(Xpath::validate("normalize-space('    abacate ') = 'abacate'", null));
        $this->assertTrue(Xpath::validate("starts-with('abacate', 'ab')", null));
        $this->assertFalse(Xpath::validate("starts-with('abacate', 'ac')", null));
    }

    #[Test]
    public function it_validates_date_functions()
    {
        $this->assertTrue(Xpath::validate("int(format-date-time(., '%H')) = 19", '2019-05-14T19:13:35.450686Z'));
        $this->assertTrue(Xpath::validate("int(format-date-time(., '%m')) = 5", '2019-05-14T19:13:35.450686Z'));
        $this->assertTrue(Xpath::validate("int(format-date-time(., '%M')) = 13", '2019-05-14T19:13:35.450686Z'));
        $this->assertTrue(Xpath::validate("int(format-date-time(., '%Y')) = 2019", '2019-05-14T19:13:35.450686Z'));
        $this->assertTrue(Xpath::validate("int(format-date-time(., '%d')) = 14", '2019-05-14T19:13:35.450686Z'));
        $this->assertTrue(Xpath::validate("format-date-time(., '%d/%m/%Y') = '14/05/2019'", '2019-05-14T19:13:35.450686Z'));
    }

    #[Test]
    public function it_validates_selected_function()
    {
        $this->assertTrue(Xpath::validate("selected('peixe abacate', .)", 'peixe'));
        $this->assertTrue(Xpath::validate("selected('peixe abacate', 'peixe')", null));
    }

    #[Test]
    public function it_validates_uuid_function(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f\-]{36}$/',
            Xpath::validate("uuid()", null, [], false)
        );
    }
}
