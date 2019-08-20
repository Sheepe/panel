<?php

namespace Tests\Unit\Services\Allocations;

use Mockery as m;
use Tests\TestCase;
use App\Models\Allocation;
use App\Services\Allocations\AllocationDeletionService;
use App\Contracts\Repository\AllocationRepositoryInterface;

class AllocationDeletionServiceTest extends TestCase
{
    /**
     * @var \App\Contracts\Repository\AllocationRepositoryInterface|\Mockery\Mock
     */
    private $repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = m::mock(AllocationRepositoryInterface::class);
    }

    /**
     * Test that an allocation is deleted.
     */
    public function testAllocationIsDeleted()
    {
        $model = factory(Allocation::class)->make();

        $this->repository->shouldReceive('delete')->with($model->id)->once()->andReturn(1);

        $response = $this->getService()->handle($model);
        $this->assertEquals(1, $response);
    }

    /**
     * Test that an exception gets thrown if an allocation is currently assigned to a server.
     *
     * @expectedException \App\Exceptions\Service\Allocation\ServerUsingAllocationException
     */
    public function testExceptionThrownIfAssignedToServer()
    {
        $model = factory(Allocation::class)->make(['server_id' => 123]);

        $this->getService()->handle($model);
    }

    /**
     * Return an instance of the service with mocked injections.
     *
     * @return \App\Services\Allocations\AllocationDeletionService
     */
    private function getService(): AllocationDeletionService
    {
        return new AllocationDeletionService($this->repository);
    }
}
