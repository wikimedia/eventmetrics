<?php
/**
 * This file contains only the FormatExtensionTest class.
 */

namespace AppBundle\Twig;

use Krinkle\Intuition\Intuition;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use DateTime;
use Tests\AppBundle\GrantMetricsTestCase;

/**
 * Tests for the FormatExtension class.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class FormatExtensionTest extends GrantMetricsTestCase
{

    /** @var \AppBundle\Twig\FormatExtension Instance of class */
    protected $formatExtension;

    /**
     * Set class instance.
     */
    public function setUp()
    {
        parent::setUp();
        static::bootKernel();
        $stack = new RequestStack();
        $session = new Session();
        $intuition = new Intuition();
        $this->formatExtension = new FormatExtension(static::$container, $stack, $session, $intuition);
    }

    /**
     * Format number as a diff size.
     */
    public function testDiffFormat()
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
    public function testPercentFormat()
    {
        static::assertEquals('45%', $this->formatExtension->percentFormat(45));
        static::assertEquals('30%', $this->formatExtension->percentFormat(30, null, 3));
        static::assertEquals('33.33%', $this->formatExtension->percentFormat(2, 6, 2));
        static::assertEquals('25%', $this->formatExtension->percentFormat(2, 8));
    }

    /**
     * Format a time duration as humanized string.
     */
    public function testFormatDuration()
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
    public function testNumberFormat()
    {
        static::assertEquals('1,234', $this->formatExtension->numberFormat(1234));
        static::assertEquals('1,234.32', $this->formatExtension->numberFormat(1234.316, 2));
        static::assertEquals('50', $this->formatExtension->numberFormat(50.0000, 4));
    }

    /**
     * Format a date.
     */
    public function testDateFormat()
    {
        // Localized.
        static::assertEquals(
            '2/1/17, 11:45 PM',
            $this->formatExtension->dateFormat(new DateTime('2017-02-01 23:45:34'))
        );
        static::assertEquals(
            '8/12/15, 11:45 AM',
            $this->formatExtension->dateFormat('2015-08-12 11:45:50')
        );

        // ISO 8601.
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
    public function testCapitalizeFirst()
    {
        static::assertEquals('Foo', $this->formatExtension->ucfirst('foo'));
        static::assertEquals('Bar', $this->formatExtension->ucfirst('Bar'));
    }

    /**
     * Wikifying a string.
     */
    public function testWikify()
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
}
