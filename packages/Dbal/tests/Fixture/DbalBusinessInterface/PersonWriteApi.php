<?php

declare(strict_types=1);

namespace Test\Ecotone\Dbal\Fixture\DbalBusinessInterface;

use Ecotone\Dbal\Attribute\DbalParameter;
use Ecotone\Dbal\Attribute\DbalWriteBusinessMethod;
use Ecotone\Messaging\Conversion\MediaType;

interface PersonWriteApi
{
    #[DbalWriteBusinessMethod('INSERT INTO persons VALUES (:personId, :name, DEFAULT)')]
    public function insert(int $personId, string $name): void;

    #[DbalWriteBusinessMethod('INSERT INTO persons VALUES (:personId, :name, DEFAULT)')]
    public function insertWithParameterName(
        #[DbalParameter(name: 'personId')] int $id,
        string $name
    ): void;

    #[DbalWriteBusinessMethod('UPDATE persons SET name = :name WHERE person_id = :personId')]
    public function changeName(int $personId, string $name): int;

    /**
     * @param string[] $roles
     */
    #[DbalWriteBusinessMethod('UPDATE persons SET roles = :roles WHERE person_id = :personId')]
    public function changeRoles(
        int $personId,
        #[DbalParameter(convertToMediaType: MediaType::APPLICATION_JSON)] array $roles
    ): void;

    /**
     * @param PersonRole[] $roles
     */
    #[DbalWriteBusinessMethod('UPDATE persons SET roles = :roles WHERE person_id = :personId')]
    public function changeRolesWithValueObjects(
        int $personId,
        #[DbalParameter(convertToMediaType: MediaType::APPLICATION_JSON)] array $roles
    ): void;

    #[DbalWriteBusinessMethod('INSERT INTO persons VALUES (:personId, :name, DEFAULT)')]
    public function insertWithExpression(
        int $personId,
        #[DbalParameter(expression: 'payload.toLowerCase()')] PersonName $name
    ): void;

    #[DbalWriteBusinessMethod('INSERT INTO persons VALUES (:personId, :name, DEFAULT)')]
    public function insertWithServiceExpression(
        int $personId,
        #[DbalParameter(expression: "reference('converter').normalize(payload)")] PersonName $name
    ): void;

    #[DbalWriteBusinessMethod('INSERT INTO persons VALUES (:personId, :name, :roles)')]
    #[DbalParameter(name: 'roles', expression: "['ROLE_ADMIN']", convertToMediaType: MediaType::APPLICATION_JSON)]
    public function registerAdmin(int $personId, string $name): void;

    #[DbalWriteBusinessMethod('INSERT INTO persons VALUES (:personId, :name, :roles)')]
    #[DbalParameter(name: 'roles', expression: "name === 'Johny' ? ['ROLE_ADMIN'] : []", convertToMediaType: MediaType::APPLICATION_JSON)]
    public function registerUsingMethodParameters(int $personId, string $name): void;
}
