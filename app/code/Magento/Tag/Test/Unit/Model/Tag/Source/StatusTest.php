<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\Tag\Source;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Tag\Api\Data\TagInterface;
use Magento\Tag\Model\Tag\Source\Status;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for the Tag Status source model.
 */
class StatusTest extends TestCase
{
    /**
     * @var Status
     */
    private Status $status;

    protected function setUp(): void
    {
        $objectManagerHelper = new ObjectManagerHelper($this);
        $this->status = $objectManagerHelper->getObject(Status::class);
    }

    public function testToOptionArrayReturnsAllStatuses(): void
    {
        $options = $this->status->toOptionArray();

        $this->assertIsArray($options);
        $this->assertCount(3, $options);

        $values = array_map(static fn (array $o): int => (int)$o['value'], $options);
        $labels = array_map(static fn (array $o): string => (string)$o['label'], $options);

        $this->assertSame(
            [
                TagInterface::STATUS_APPROVED,
                TagInterface::STATUS_PENDING,
                TagInterface::STATUS_DISABLED,
            ],
            $values
        );
        $this->assertSame(['Approved', 'Pending', 'Disabled'], $labels);

        foreach ($options as $option) {
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey('value', $option);
        }
    }

    public function testToOptionArrayIsCachedAcrossCalls(): void
    {
        $first = $this->status->toOptionArray();
        $second = $this->status->toOptionArray();

        $this->assertSame($first, $second);
    }

    public function testGetAllOptionsReturnsSameAsToOptionArray(): void
    {
        $this->assertSame($this->status->toOptionArray(), $this->status->getAllOptions());
    }

    public function testGetStatusesArrayReturnsExpectedLabels(): void
    {
        $statuses = $this->status->getStatusesArray();

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
        $options = $this->status->getStatusesOptionsArray();

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
