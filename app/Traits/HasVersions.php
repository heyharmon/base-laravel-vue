<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasVersions
{
	/**
	 * Boot the trait.
	 */
	protected static function bootHasVersions()
	{
		// Create the initial version when a model is created
		static::created(function ($model) {
			$model->createInitialVersion();
		});

		// Create a new version when a model is updated
		static::updating(function ($model) {
			$model->createVersionFromChanges();
		});
	}

	/**
	 * Create the initial version for a new model.
	 */
	protected function createInitialVersion()
	{
		if ($this->hasVersionableChanges()) {
			$this->versions()->create([
				$this->getVersionForeignKey() => $this->id,
				'version_number' => 1,
				'data' => $this->getAttributes(),
			]);

			$this->current_version = 1;

			$this->saveQuietly();
		}
	}

	/**
	 * Get all versions of this model.
	 */
	public function versions(): HasMany
	{
		return $this->hasMany($this->getVersionModel())->latest();
	}

	/**
	 * Check if any versionable attributes have changed.
	 */
	protected function hasVersionableChanges(?array $changes = null)
	{
		$changes = $changes ?? $this->getDirty();

		// Check if any versionable attributes have changed
		$versionableChanges = array_intersect_key($changes, array_flip($this->versionableAttributes));

		return count($versionableChanges) > 0;
	}

	/**
	 * Create a version if versionable attributes have changed.
	 */
	protected function createVersionFromChanges()
	{
		$changes = $this->getDirty();

		if ($this->hasVersionableChanges($changes)) {
			// Find the highest version number and increment by 1
			$maxVersionNumber = $this->versions()->max('version_number') ?? 0;
			$newVersionNumber = $maxVersionNumber + 1;

			// We need to apply the changes to the attributes before creating the version
			$attributes = $this->getAttributes();
			foreach ($changes as $key => $value) {
				$attributes[$key] = $value;
			}

			$this->versions()->create([
				$this->getVersionForeignKey() => $this->id,
				'version_number' => $newVersionNumber,
				'data' => $attributes,
			]);

			// Update the current version number
			$this->current_version = $newVersionNumber;
		}
	}

	/**
	 * Revert to a specific version.
	 */
	public function revertToVersion($version)
	{
		// Ensure the version belongs to this model
		$foreignKey = $this->getVersionForeignKey();
		if ($version->{$foreignKey} !== $this->id) {
			return false;
		}

		DB::transaction(function () use ($version) {
			// Update the model with the version's data
			$versionData = $version->data->toArray();

			// Only update versionable attributes
			foreach ($this->versionableAttributes as $attribute) {
				if (isset($versionData[$attribute])) {
					$this->{$attribute} = $versionData[$attribute];
				}
			}

			// Update the current version to the target version number
			$this->current_version = $version->version_number;

			// Save without triggering another version
			$this->saveQuietly();
		});

		return true;
	}

	/**
	 * Save the model without triggering versioning.
	 */
	public function saveQuietly(array $options = [])
	{
		return static::withoutEvents(function () use ($options) {
			return $this->save($options);
		});
	}

	/**
	 * Get the version model class name.
	 */
	protected function getVersionModel(): string
	{
		return $this->versionModel ?? (get_class($this) . 'Version');
	}

	/**
	 * Get the foreign key for the version relationship.
	 */
	protected function getVersionForeignKey(): string
	{
		$modelClass = class_basename($this);
		return lcfirst($modelClass) . '_id';
	}
}
