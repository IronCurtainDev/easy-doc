<?php

namespace EasyDoc\Contracts;

/**
 * Interface for models that define extra API columns for documentation.
 *
 * Implement this interface to add virtual/computed columns to your model's
 * Swagger schema that aren't stored in the database.
 *
 * Example:
 * ```php
 * use EasyDoc\Contracts\HasExtraApiColumns;
 *
 * class User extends Model implements HasExtraApiColumns
 * {
 *     public function addExtraAPIColumns(): array
 *     {
 *         return [
 *             'access_token' => type('string')->nullable(),
 *             'token_type' => type('string')->default('Bearer'),
 *             'places' => type('array')->of(Place::class),
 *         ];
 *     }
 * }
 * ```
 */
interface HasExtraApiColumns
{
    /**
     * Define extra API columns that appear in Swagger documentation.
     *
     * These columns are not in the database but are part of API responses.
     *
     * @return array<string, \EasyDoc\Docs\SchemaType>
     */
    public function addExtraAPIColumns(): array;
}
