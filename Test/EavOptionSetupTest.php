<?php


namespace VinaiKopp\EavOptionSetup\Test;

use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Api\Data\AttributeOptionLabelInterface;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Framework\App\State as AppState;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use VinaiKopp\EavOptionSetup\Setup\EavOptionSetup;
use PHPUnit\Framework\TestCase;

/**
 * @covers \VinaiKopp\EavOptionSetup\Setup\EavOptionSetup
 */
class EavOptionSetupTest extends TestCase
{
    /**
     * @var EavOptionSetup
     */
    private $optionSetup;

    /**
     * @var AttributeRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockAttributeRepository;

    /**
     * @var AttributeOptionManagementInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockAttributeOptionManagementService;

    /**
     * @var AttributeOptionInterfaceFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockAttributeOptionFactory;

    /**
     * @var AttributeOptionLabelInterfaceFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockAttributeOptionLabelFactory;

    /**
     * @var AppState|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockAppState;


    /**
     * @var ResourceConnection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mockResourceConnection;

    /**
     * @param string $testLabel
     * @param int $testSortOrder
     * @return AttributeOptionInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createMockOptionLabel($testLabel, $testSortOrder)
    {
        $mockOption = $this->createMock(AttributeOptionInterface::class);
        $mockOption->method('getLabel')->willReturn($testLabel);
        $mockOption->method('getSortOrder')->willReturn($testSortOrder);
        return $mockOption;
    }

    private function setSpecifiedAttributeExistsFixture()
    {
        $dummyAttributeId = 111;
        $mockAttribute = $this->createMock(AttributeInterface::class);
        $mockAttribute->method('getAttributeId')->willReturn($dummyAttributeId);
        $this->mockAttributeRepository->method('get')->willReturn($mockAttribute);
    }

    private function expectNewOptionWithStoreLabelToBeCreated()
    {
        $mockOption = $this->createMock(AttributeOptionInterface::class);
        $mockOption->expects($this->once())->method('setStoreLabels')->willReturnCallback(function ($args) {
            $this->assertInternalType('array', $args);
            $this->assertCount(1, $args);
            $this->assertContainsOnlyInstancesOf(AttributeOptionLabelInterface::class, $args);
        });
        $this->mockAttributeOptionFactory->method('create')->willReturn($mockOption);
    }

    protected function setUp()
    {
        $this->mockAttributeRepository = $this->createMock(AttributeRepositoryInterface::class);
        $this->mockAttributeOptionManagementService = $this->createMock(AttributeOptionManagementInterface::class);
        $this->mockAttributeOptionFactory = $this->createMock(
            AttributeOptionInterfaceFactory::class,
            ['create'],
            [],
            '',
            false
        );

        $this->mockAttributeOptionLabelFactory = $this->createMock(
            AttributeOptionLabelInterfaceFactory::class,
            ['create'],
            [],
            '',
            false
        );

        $this->mockAppState = $this->createMock(AppState::class, [], [], '', false);

        $this->mockResourceConnection = $this->createMock(ResourceConnection::class);

        $this->optionSetup = new EavOptionSetup(
            $this->mockAttributeRepository,
            $this->mockAttributeOptionManagementService,
            $this->mockAttributeOptionFactory,
            $this->mockAttributeOptionLabelFactory,
            $this->mockAppState,
            $this->mockResourceConnection
        );
    }

    /**
     * @test
     */
    public function itShouldThrowIfAdminLabelIsSpecifiedAsStoreScopeLabel()
    {
        $this->expectException(\RuntimeException::class);

        $this->optionSetup->addAttributeOptionIfNotExistsWithStoreLabels(
            'entity_type',
            'attribute_code',
            'Default Store Label',
            [0 => 'Store Scope Label with Admin Scope ID']
        );
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionIfANonNumericStoreIdWasSpecified()
    {
        $this->expectException(\RuntimeException::class);

        $this->optionSetup->addAttributeOptionIfNotExistsWithStoreLabels(
            'entity_type',
            'attribute_code',
            'Default Store Label',
            ['test' => 'Store Scope Label with Admin Scope ID']
        );
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionIfTheAttributeIsNotKnown()
    {
        $this->expectException(\RuntimeException::class);
        $this->mockAttributeRepository->method('get')->willThrowException(new \Exception('Test Exception'));
        $this->optionSetup->addAttributeOptionIfNotExists(
            'entity_type',
            'attribute_code',
            'Default Option Label'
        );
    }

    /**
     * @test
     * @dataProvider unexpectedReturnValueProvider
     */
    public function itShouldThrowAnExceptionIfTheRepositoryReturnsAnUnexpectedResult($returnValue)
    {
        $this->expectException(\RuntimeException::class);
        $this->mockAttributeRepository->method('get')->willReturn($returnValue);
        $this->optionSetup->addAttributeOptionIfNotExists(
            'entity_type',
            'attribute_code',
            'Default Option Label'
        );
    }

    public function unexpectedReturnValueProvider()
    {
        return [
            'null' => [null],
            'string' => ['a string'],
            'empty attribute' => [$this->createMock(AttributeInterface::class)]
        ];
    }

    /**
     * @test
     */
    public function itShouldNotAddKnownAttributeOptions()
    {
        $mockAttribute = $this->createMock(AttributeInterface::class);
        $mockAttribute->method('getAttributeId')->willReturn(111);

        $this->mockAttributeRepository->method('get')->willReturn($mockAttribute);

        $this->mockAttributeOptionManagementService->method('getItems')
            ->willReturn([
                $this->createMockOptionLabel('Option 1', 100),
                $this->createMockOptionLabel('Option 2', 200),
                $this->createMockOptionLabel('Option 3', 300),
            ]);

        $this->mockAttributeOptionManagementService->expects($this->never())->method('add');

        $this->optionSetup->addAttributeOptionIfNotExists('entity_code', 'attribute_code', 'Option 2');
    }

    /**
     * @test
     */
    public function itShouldAddUnknownAttributeOptions()
    {
        $this->setSpecifiedAttributeExistsFixture();

        $this->mockAttributeOptionFactory->method('create')->willReturn(
            $this->createMock(AttributeOptionInterface::class)
        );

        $this->mockAttributeOptionManagementService->method('getItems')
            ->willReturn([
                $this->createMockOptionLabel('Option 1', 100),
                $this->createMockOptionLabel('Option 2', 200),
                $this->createMockOptionLabel('Option 3', 300),
            ]);

        $this->mockAttributeOptionManagementService->expects($this->once())->method('add');

        $this->optionSetup->addAttributeOptionIfNotExists('entity_code', 'attribute_code', 'Option 4');
    }

    /**
     * @test
     */
    public function itShouldAddStoreLabelInstances()
    {
        $this->setSpecifiedAttributeExistsFixture();

        $this->expectNewOptionWithStoreLabelToBeCreated();

        $this->mockAttributeOptionLabelFactory->method('create')->willReturn(
            $this->createMock(AttributeOptionLabelInterface::class)
        );

        $this->mockAttributeOptionManagementService->method('getItems')
            ->willReturn([
                $this->createMockOptionLabel('Option 1', 100),
                $this->createMockOptionLabel('Option 2', 200),
                $this->createMockOptionLabel('Option 3', 300),
            ]);

        $this->mockAttributeOptionManagementService->expects($this->once())->method('add');

        $testStoreId = 1;
        $this->optionSetup->addAttributeOptionIfNotExistsWithStoreLabels(
            'entity_code',
            'attribute_code',
            'Option 4',
            [$testStoreId => 'Option 4 Store 1 Label']
        );
    }

    /**
     * @test
     */
    public function itShouldTryToSetTheAdminScopeAsAWorkaroundForIssue1405()
    {
        $this->setSpecifiedAttributeExistsFixture();

        $this->mockAttributeOptionFactory->method('create')->willReturn(
            $this->createMock(AttributeOptionInterface::class)
        );

        $this->mockAttributeOptionManagementService->method('getItems')
            ->willReturn([
                $this->createMockOptionLabel('Option 1', 100),
                $this->createMockOptionLabel('Option 2', 200),
                $this->createMockOptionLabel('Option 3', 300),
            ]);

        $this->mockAppState->expects($this->once())->method('setAreaCode');

        $this->optionSetup->addAttributeOptionIfNotExists('entity_code', 'attribute_code', 'Option 4');

    }
}
