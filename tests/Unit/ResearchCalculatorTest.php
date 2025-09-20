<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Entity\ResearchDefinition;
use App\Domain\Service\ResearchCalculator;
use PHPUnit\Framework\TestCase;

class ResearchCalculatorTest extends TestCase
{
    private ResearchCalculator $calculator;

    private ResearchDefinition $definition;

    protected function setUp(): void
    {
        $this->calculator = new ResearchCalculator();
        $this->definition = new ResearchDefinition(
            'hyperdrive',
            'Propulsion hyperspatial',
            'Propulsion',
            'Accélère les déplacements interstellaires.',
            ['metal' => 200, 'crystal' => 150, 'hydrogen' => 50],
            120,
            1.75,
            1.6,
            10,
            ['propulsion_basic' => 3, 'weapon_light' => 2],
            4,
            '/assets/research/hyperdrive.png'
        );
    }

    public function testCheckRequirementsSatisfied(): void
    {
        $catalog = [
            'propulsion_basic' => ['label' => 'Propulsion spatiale'],
            'weapon_light' => ['label' => 'Armes légères'],
        ];

        $result = $this->calculator->checkRequirements(
            $this->definition,
            ['propulsion_basic' => 3, 'weapon_light' => 2],
            4,
            $catalog
        );

        self::assertTrue($result['ok']);
        self::assertSame([], $result['missing']);
    }

    public function testCheckRequirementsReportsMissingLabAndResearch(): void
    {
        $catalog = [
            'propulsion_basic' => ['label' => 'Propulsion spatiale'],
            'weapon_light' => ['label' => 'Armes légères'],
        ];

        $result = $this->calculator->checkRequirements(
            $this->definition,
            ['propulsion_basic' => 3],
            2,
            $catalog
        );

        self::assertFalse($result['ok']);
        self::assertCount(2, $result['missing']);

        $labRequirement = $result['missing'][0];
        self::assertSame('building', $labRequirement['type']);
        self::assertSame('research_lab', $labRequirement['key']);
        self::assertSame(4, $labRequirement['level']);
        self::assertSame(2, $labRequirement['current']);

        $researchRequirement = $result['missing'][1];
        self::assertSame('research', $researchRequirement['type']);
        self::assertSame('weapon_light', $researchRequirement['key']);
        self::assertSame('Armes légères', $researchRequirement['label']);
        self::assertSame(2, $researchRequirement['level']);
        self::assertSame(0, $researchRequirement['current']);
    }

    public function testNextCostAndTimeScaleWithLevel(): void
    {
        $level0Cost = $this->calculator->nextCost($this->definition, 0);
        $level2Cost = $this->calculator->nextCost($this->definition, 2);

        self::assertSame(['metal' => 200, 'crystal' => 150, 'hydrogen' => 50], $level0Cost);
        self::assertGreaterThan($level0Cost['metal'], $level2Cost['metal']);
        self::assertGreaterThan($level0Cost['crystal'], $level2Cost['crystal']);
        self::assertGreaterThan($level0Cost['hydrogen'], $level2Cost['hydrogen']);

        $baseTime = $this->calculator->nextTime($this->definition, 0);
        $levelThreeTime = $this->calculator->nextTime($this->definition, 3);

        self::assertSame(120, $baseTime);
        self::assertGreaterThan($baseTime, $levelThreeTime);
    }
}
