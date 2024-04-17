<?php declare( strict_types=1 );

namespace App\EventSubscriber;

use App\Model\EventCategory;
use App\Repository\EventCategoryRepository;

/**
 * CategorySubscriber automatically sets the category ID on EventCategories before they are persisted.
 * This isn't usually needed because categories are (currently) only created through the HTML form, which has it's own
 * validation for category ID. This is here for when categories are persisted by other means.
 */
class CategorySubscriber {

	/**
	 * Constructor for CategorySubscriber.
	 * @param EventCategoryRepository $eventCategoryRepo
	 */
	public function __construct( private readonly EventCategoryRepository $eventCategoryRepo ) {
	}

	/**
	 * Set the category ID on the EventCategory upon persisting.
	 * @param EventCategory $category
	 */
	public function prePersist( EventCategory $category ): void {
		if ( $category->getCategoryId() !== null ) {
			return;
		}

		$catId = $this->eventCategoryRepo
			->getCategoryId( $category->getDomain(), $category->getTitle( true ) );

		if ( is_int( $catId ) ) {
			$category->setCategoryId( $catId );
		}
	}
}
