<?php
/**
 * This file contains only the FormatExtensionTest class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Twig;

use AppBundle\Twig\FormatExtension;
use DateTime;
use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\AppBundle\EventMetricsTestCase;

/**
 * Tests for the FormatExtension class.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class FormatExtensionTest extends EventMetricsTestCase
{

    /** @var \AppBundle\Twig\FormatExtension Instance of class */
    protected $formatExtension;

    /** @var Intuition */
    protected $intuition;

    /**
     * Set class instance.
     */
    public function setUp(): void
    {
        parent::setUp();
        static::bootKernel();
        $stack = new RequestStack();
        $session = new Session();
        $this->intuition = new Intuition();
        $this->formatExtension = new FormatExtension(static::$container, $stack, $session, $this->intuition);
    }

    /**
     * Format number as a diff size.
     */
    public function testDiffFormat(): void
    {
        static::assertEquals(
            "<span class='diff-pos'>3,000</span>",
            $this->formatExtension->diffFormat(3000)
        );
        static::assertEquals(
            "<span class='diff-neg'>-20,000</span>",
            $this->formatExtension->diffFormat(-20000)
        );
        static::assertEquals(
            "<span class='diff-zero'>0</span>",
            $this->formatExtension->diffFormat(0)
        );
    }

    /**
     * Format number as a percentage.
     */
    public function testPercentFormat(): void
    {
        static::assertEquals('45%', $this->formatExtension->percentFormat(45));
        static::assertEquals('30%', $this->formatExtension->percentFormat(30, null, 3));
        static::assertEquals('33.33%', $this->formatExtension->percentFormat(2, 6, 2));
        static::assertEquals('25%', $this->formatExtension->percentFormat(2, 8));
    }

    /**
     * Format a time duration as humanized string.
     */
    public function testFormatDuration(): void
    {
        static::assertEquals(
            [30, 'num-seconds'],
            $this->formatExtension->formatDuration(30, false)
        );
        static::assertEquals(
            [1, 'num-minutes'],
            $this->formatExtension->formatDuration(70, false)
        );
        static::assertEquals(
            [50, 'num-minutes'],
            $this->formatExtension->formatDuration(3000, false)
        );
        static::assertEquals(
            [2, 'num-hours'],
            $this->formatExtension->formatDuration(7500, false)
        );
        static::assertEquals(
            [10, 'num-days'],
            $this->formatExtension->formatDuration(864000, false)
        );
    }

    /**
     * Format a number.
     */
    public function testNumberFormat(): void
    {
        static::assertEquals('1,234', $this->formatExtension->numberFormat(1234));
        static::assertEquals('1,234.32', $this->formatExtension->numberFormat(1234.316, 2));
        static::assertEquals('50', $this->formatExtension->numberFormat(50.0000, 4));
    }

    /**
     * Format a date.
     */
    public function testDateFormat(): void
    {
        // Default of English uses ISO8601 format.
        $this->assertEquals('en', $this->intuition->getLang());
        static::assertEquals(
            '2017-02-01 23:45',
            $this->formatExtension->dateFormat(new DateTime('2017-02-01 23:45:34'))
        );
        // Change to another locale and check format.
        $this->intuition->setLang('pl');
        // As a Datetime object.
        static::assertEquals(
            '01.02.2017, 23:45',
            $this->formatExtension->dateFormat(new DateTime('2017-02-01 23:45:34'))
        );
        // As a string.
        static::assertEquals(
            '12.08.2015, 11:45',
            $this->formatExtension->dateFormat('2015-08-12 11:45:50')
        );
    }

    /**
     * Format a date according to ISO8601.
     */
    public function testDateFormatStd(): void
    {
        static::assertEquals(
            '2017-02-01 23:45',
            $this->formatExtension->dateFormatStd(new DateTime('2017-02-01 23:45:34'))
        );
        static::assertEquals(
            '2015-08-12 11:45',
            $this->formatExtension->dateFormatStd('2015-08-12 11:45:50')
        );
    }

    /**
     * Capitalizing first letter.
     */
    public function testCapitalizeFirst(): void
    {
        static::assertEquals('Foo', $this->formatExtension->ucfirst('foo'));
        static::assertEquals('Bar', $this->formatExtension->ucfirst('Bar'));
    }

    /**
     * Wikifying a string.
     */
    public function testWikify(): void
    {
        $wikitext = '<script>alert("XSS baby")</script> [[test page]]';
        static::assertEquals(
            "&lt;script&gt;alert(\"XSS baby\")&lt;/script&gt; ".
                "<a target='_blank' href='https://test.example.org/wiki/Test_page'>test page</a>",
            $this->formatExtension->wikify($wikitext, 'test.example')
        );

        $wikitext = '/* My section */ Editing a specific &quot;section&quot;';
        static::assertEquals(
            "<a target='_blank' href='https://test.example.org/wiki/My_fun_page#My_section'>".
                "&rarr;</a><em class='text-muted'>My section:</em> Editing a specific \"section\"",
            $this->formatExtension->wikify($wikitext, 'test.example', 'my fun page')
        );
    }

    /**
     * @covers \AppBundle\Twig\FormatExtension::csv()
     */
    public function testCsv(): void
    {
        static::assertEquals('"Foo\'s ""Bar"""', $this->formatExtension->csv('Foo\'s "Bar"'));
    }
}
