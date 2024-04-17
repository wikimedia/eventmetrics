<?php declare( strict_types=1 );

namespace App\Form;

use App\Model\Organizer;
use App\Repository\OrganizerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * A ProgramType is the type of form used when creating and updating a Program.
 */
class ProgramType extends AbstractType {

	/**
	 * ProgramType constructor.
	 * @param OrganizerRepository $organizerRepo
	 */
	public function __construct( private readonly OrganizerRepository $organizerRepo ) {
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder, array $options ): void {
		$builder->add( 'title', TextType::class, [
				'constraints' => [
					new NotBlank(),
				],
			] )
			->add( 'organizers', CollectionType::class, [
				'entry_type' => TextType::class,
				'allow_add' => true,
				'allow_delete' => true,
				'delete_empty' => true,
				'required' => false,
				'constraints' => [ new Valid() ],
			] )
			->add( 'submit', SubmitType::class );

		$builder->get( 'organizers' )
			->addModelTransformer( $this->getCallbackTransformer() );
	}

	/**
	 * Transform organizer data to or from the form. This essentially pulls in the username from the user ID,
	 * and sets the user ID before persisting so that the username can be validated.
	 * @return CallbackTransformer
	 */
	private function getCallbackTransformer(): CallbackTransformer {
		return new CallbackTransformer(
			// Transform to the form.
			static function ( Collection $organizerObjects ): array {
				return array_map( static function ( Organizer $organizer ) {
					return $organizer->getUsername();
				}, $organizerObjects->toArray() );
			},
			// Transform from the form.
			function ( array $organizerNames ): Collection {
				return ( new ArrayCollection( $organizerNames ) )->map( function ( string $organizerName ) {
					return $this->organizerRepo->getOrganizerByUsername( $organizerName );
				} );
			}
		);
	}
}
