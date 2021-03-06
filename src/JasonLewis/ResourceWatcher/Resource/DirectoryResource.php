<?php namespace JasonLewis\ResourceWatcher\Resource;

use DirectoryIterator;
use JasonLewis\ResourceWatcher\Event;
use Illuminate\Filesystem\Filesystem;

class DirectoryResource extends Resource {

	/**
	 * Array of directory resources descendants.
	 * 
	 * @var array
	 */
	protected $descendants = array();

	/**
	 * Setup the directory resource.
	 * 
	 * @return void
	 */
	public function setupDirectory()
	{
		$this->descendants = $this->detectDirectoryDescendants();
	}

	/**
	 * Detect any changes to the resource.
	 * 
	 * @return array
	 */
	public function detectChanges()
	{
		$events = parent::detectChanges();

		// When a descendant file is created or deleted a modified event is fired on the
		// directory. This is the only way a directory will receive a modified event and
		// will thus result in two events being fired for a single descendant modification
		// within the directory. This will clear the events if we got a modified event.
		if ($events and $events[0]->getCode() == Event::RESOURCE_MODIFIED)
		{
			$events = array();
		}

		foreach ($this->descendants as $key => $descendant)
		{
			$descendantEvents = $descendant->detectChanges();

			foreach ($descendantEvents as $event)
			{
				if ($event instanceof Event and $event->getCode() == Event::RESOURCE_DELETED)
				{
					unset($this->descendants[$key]);
				}
			}

			$events = array_merge($events, $descendantEvents);
		}

		// If this directory still exists we'll check the directories descendants again for any
		// new descendants.
		if ($this->exists)
		{
			foreach ($this->detectDirectoryDescendants() as $key => $descendant)
			{
				if ( ! isset($this->descendants[$key]))
				{
					$this->descendants[$key] = $descendant;

					$events[] = new Event($descendant, Event::RESOURCE_CREATED);
				}
			}
		}

		return $events;
	}

	/**
	 * Detect the descendant resources of the directory.
	 * 
	 * @return array
	 */
	protected function detectDirectoryDescendants()
	{
		$descendants = array();

		foreach (new DirectoryIterator($this->resource) as $file)
		{
			if ($file->isDir() and ! $file->isDot())
			{
				$resource = new DirectoryResource($file->getRealPath(), $this->files);

				$descendants[$resource->getKey()] = $resource;
			}
			elseif ($file->isFile())
			{
				$resource = new FileResource($file->getRealPath(), $this->files);

				$descendants[$resource->getKey()] = $resource;
			}
		}

		return $descendants;
	}

	/**
	 * Get the descendants of the directory.
	 * 
	 * @return array
	 */
	public function getDescendants()
	{
		return $this->descendants;
	}

}