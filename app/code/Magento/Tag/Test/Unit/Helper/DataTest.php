<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Tag\Api\Data\TagInterface;
use Magento\Tag\Helper\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for the Tag data helper.
 */
class DataTest extends TestCase
{
    /**
     * @var Data
     */
    private Data $helper;

    /**
     * @var Context|MockObject
     */
    private $contextMock;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $objectManagerHelper = new ObjectManagerHelper($this);
        $this->helper = $objectManagerHelper->getObject(
            Data::class,
            ['context' => $this->contextMock]
        );
    }

    public function testExtractTagsEmptyString(): void
    {
        // preg_replace on empty string yields empty string, which explode("\n", "")
        // returns as a single-element array containing an empty string.
        $this->assertSame([''], $this->helper->extractTags(''));
    }

    public function testExtractTagsSingleWord(): void
    {
        $this->assertSame(['foo'], $this->helper->extractTags('foo'));
    }

    public function testExtractTagsMultipleWords(): void
    {
        $this->assertSame(['foo', 'bar', 'baz'], $this->helper->extractTags('foo bar baz'));
    }

    public function testExtractTagsSingleQuotedPhrase(): void
    {
        // The regex preserves the single-quoted phrase as a single element and splits
        // whitespace into separator newlines. The whitespace *after* a quoted phrase
        // produces an extra empty string element because the quoted phrase replacement
        // already appends a newline.
        $this->assertSame(
            ["'hello world'", '', 'foo'],
            $this->helper->extractTags("'hello world' foo")
        );
    }

    public function testExtractTagsMultipleSpaces(): void
    {
        // \s+ collapses contiguous whitespace into a single separator newline.
        $this->assertSame(['foo', 'bar'], $this->helper->extractTags('foo   bar'));
    }

    public function testExtractTagsNestedQuotesEdgeCase(): void
    {
        // Two quoted phrases separated by a space. The regex appends a trailing
        // newline after each quoted phrase, so a final empty string appears when
        // the input ends on a quoted phrase.
        $this->assertSame(
            ["'one two'", '', "'three four'", ''],
            $this->helper->extractTags("'one two' 'three four'")
        );
    }

    public function testCleanTagsTrimsAndFiltersEmpty(): void
    {
        $result = $this->helper->cleanTags(['foo', '  ', '', ' bar ', "\t"]);
        $this->assertSame(['foo', 'bar'], array_values($result));
    }

    public function testCleanTagsStripsSurroundingQuotes(): void
    {
        $result = $this->helper->cleanTags(["'hello world'", "'solo'", "no quotes"]);
        $this->assertSame(['hello world', 'solo', 'no quotes'], array_values($result));
    }

    public function testGetStatusesArrayReturnsExpectedLabels(): void
    {
        $statuses = $this->helper->getStatusesArray();

        $this->assertIsArray($statuses);
        $this->assertCount(3, $statuses);
        $this->assertArrayHasKey(TagInterface::STATUS_APPROVED, $statuses);
        $this->assertArrayHasKey(TagInterface::STATUS_PENDING, $statuses);
        $this->assertArrayHasKey(TagInterface::STATUS_DISABLED, $statuses);

        $this->assertSame('Approved', (string)$statuses[TagInterface::STATUS_APPROVED]);
        $this->assertSame('Pending', (string)$statuses[TagInterface::STATUS_PENDING]);
        $this->assertSame('Disabled', (string)$statuses[TagInterface::STATUS_DISABLED]);
    }

    public function testGetStatusesOptionsArrayReturnsExpectedShape(): void
    {
        $options = $this->helper->getStatusesOptionsArray();

        $this->assertIsArray($options);
        $this->assertCount(3, $options);

        $values = array_map(static fn (array $o): int => (int)$o['value'], $options);
        $labels = array_map(static fn (array $o): string => (string)$o['label'], $options);

        $this->assertSame(
            [
                TagInterface::STATUS_DISABLED,
                TagInterface::STATUS_PENDING,
                TagInterface::STATUS_APPROVED,
            ],
            $values
        );
        $this->assertSame(['Disabled', 'Pending', 'Approved'], $labels);
    }
}
