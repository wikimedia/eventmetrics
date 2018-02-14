<?php
/**
 * This file contains only the FormatExtensionTest class.
 */

namespace AppBundle\Twig;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use AppBundle\Twig\AppExtension;
use AppBundle\Twig\Extension;
use DateTime;

/**
 * Tests for the FormatExtension class.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class FormatExtensionTest extends WebTestCase
{
    /** @var Container The Symfony container. */
    protected $container;

    /** @var AppBundle\Twig\FormatExtension Instance of class */
    protected $formatExtension;

    /**
     * Set class instance.
     */
    public function setUp()
    {
        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
        $stack = new RequestStack();
        $session = new Session();
        $this->formatExtension = new FormatExtension($this->container, $stack, $session);
    }

    /**
     * Format number as a diff size.
     */
    public function testDiffFormat()
    {
        $this->assertEquals(
            "<span class='diff-pos'>3,000</span>",
            $this->formatExtension->diffFormat(3000)
        );
        $this->assertEquals(
            "<span class='diff-neg'>-20,000</span>",
            $this->formatExtension->diffFormat(-20000)
        );
        $this->assertEquals(
            "<span class='diff-zero'>0</span>",
            $this->formatExtension->diffFormat(0)
        );
    }

    /**
     * Format number as a percentage.
     */
    public function testPercentFormat()
    {
        $this->assertEquals('45%', $this->formatExtension->percentFormat(45));
        $this->assertEquals('30%', $this->formatExtension->percentFormat(30, null, 3));
        $this->assertEquals('33.33%', $this->formatExtension->percentFormat(2, 6, 2));
        $this->assertEquals('25%', $this->formatExtension->percentFormat(2, 8));
    }

    /**
     * Format a time duration as humanized string.
     */
    public function testFormatDuration()
    {
        $this->assertEquals(
            [30, 'num-seconds'],
            $this->formatExtension->formatDuration(30, false)
        );
        $this->assertEquals(
            [1, 'num-minutes'],
            $this->formatExtension->formatDuration(70, false)
        );
        $this->assertEquals(
            [50, 'num-minutes'],
            $this->formatExtension->formatDuration(3000, false)
        );
        $this->assertEquals(
            [2, 'num-hours'],
            $this->formatExtension->formatDuration(7500, false)
        );
        $this->assertEquals(
            [10, 'num-days'],
            $this->formatExtension->formatDuration(864000, false)
        );
    }

    /**
     * Format a number.
     */
    public function testNumberFormat()
    {
        $this->assertEquals('1,234', $this->formatExtension->numberFormat(1234));
        $this->assertEquals('1,234.32', $this->formatExtension->numberFormat(1234.316, 2));
        $this->assertEquals('50', $this->formatExtension->numberFormat(50.0000, 4));
    }

    /**
     * Format a date.
     */
    public function testDateFormat()
    {
        // Localized.
        $this->assertEquals(
            '2/1/17, 11:45 PM',
            $this->formatExtension->dateFormat(new DateTime('2017-02-01 23:45:34'))
        );
        $this->assertEquals(
            '8/12/15, 11:45 AM',
            $this->formatExtension->dateFormat('2015-08-12 11:45:50')
        );

        // ISO 8601.
        $this->assertEquals(
            '2017-02-01 23:45',
            $this->formatExtension->dateFormatStd(new DateTime('2017-02-01 23:45:34'))
        );
        $this->assertEquals(
            '2015-08-12 11:45',
            $this->formatExtension->dateFormatStd('2015-08-12 11:45:50')
        );
    }

    /**
     * Capitalizing first letter.
     */
    public function testCapitalizeFirst()
    {
        $this->assertEquals('Foo', $this->formatExtension->ucfirst('foo'));
        $this->assertEquals('Bar', $this->formatExtension->ucfirst('Bar'));
    }

    /**
     * Wikifying a string.
     */
    public function testWikify()
    {
        $wikitext = '<script>alert("XSS baby")</script> [[test page]]';
        $this->assertEquals(
            "&lt;script&gt;alert(\"XSS baby\")&lt;/script&gt; " .
                "<a target='_blank' href='https://test.example.org/wiki/Test_page'>test page</a>",
            $this->formatExtension->wikify($wikitext, 'test.example')
        );

        $wikitext = '/* My section */ Editing a specific &quot;section&quot;';
        $this->assertEquals(
            "<a target='_blank' href='https://test.example.org/wiki/My_fun_page#My_section'>".
                "&rarr;</a><em class='text-muted'>My section:</em> Editing a specific \"section\"",
            $this->formatExtension->wikify($wikitext, 'test.example', 'my fun page')
        );
    }
}
