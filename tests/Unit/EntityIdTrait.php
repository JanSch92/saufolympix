<?php

namespace App\Tests\Unit;

/**
 * Setzt private IDs auf Entities per Reflection — nur für Unit-Tests.
 */
trait EntityIdTrait
{
    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setValue($entity, $id);
    }
}
