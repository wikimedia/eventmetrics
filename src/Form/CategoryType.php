<?php declare( strict_types=1 );

namespace App\Form;

use App\Model\Event;
use App\Model\EventCategory;
use App\Repository\EventWikiRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * A CategoryType represents a single category row in the form.
 */
class CategoryType extends AbstractType {

	/** @var Event */
	private Event $event;

	/**
	 * CategoryType constructor.
	 * @param EventWikiRepository $ewRepo
	 */
	public function __construct( private readonly EventWikiRepository $ewRepo ) {
	}

	/**
	 * @inheritDoc
	 */
	public function buildForm( FormBuilderInterface $builder, array $options ): void {
		$this->event = $options['event'];

		$builder->add( 'domain', TextType::class, [
				'constraints' => [ new Valid() ],
				'empty_data' => '',
			] )->add( 'title', TextType::class, [
				'constraints' => [ new Valid() ],
				'empty_data' => '',
			] );

		$builder->get( 'domain' )
			->addModelTransformer( $this->getWikiCallbackTransformer() );
	}

	/**
	 * @inheritDoc
	 */
	public function configureOptions( OptionsResolver $resolver ): void {
		$resolver->setDefaults( [
			'data_class' => EventCategory::class,
			'event' => null,
			'required' => false,
			'empty_data' => function ( FormInterface $form ) {
				return new EventCategory(
					$this->event,
					$form->get( 'title' )->getData(),
					$form->get( 'domain' )->getData()
				);
			},
			'error_mapping' => [
				'categoryId' => 'title',
			],
		] );
	}

	/**
	 * Transform wiki data to or from the form.
	 * @return CallbackTransformer
	 */
	private function getWikiCallbackTransformer(): CallbackTransformer {
		return new CallbackTransformer(
			// Transform to the form.
			static function ( $domain ) {
				// No transformation needed.
				return $domain;
			},
			// Transform from the form.
			function ( $domain ) {
				// Turns 'en.wikipedia.org', 'enwiki', etc., into 'en.wikipedia'.
				return (string)$this->ewRepo->getDomainFromEventWikiInput( $domain );
			}
		);
	}
}
