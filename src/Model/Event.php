<?php declare( strict_types=1 );

namespace App\Model;

use App\Model\Traits\EventStatTrait;
use App\Model\Traits\TitleUserTrait;
use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Doctrine\ORM\Mapping as ORM;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An Event belongs to one program, and has many participants.
 * @ORM\Entity(repositoryClass="App\Repository\EventRepository")
 * @ORM\Table(
 *     name="event",
 *     indexes={
 * @ORM\Index(name="event_time", columns={"event_start", "event_end"}),
 * @ORM\Index(name="event_title", columns={"event_title"}),
 * @ORM\Index(name="event_program", columns={"event_program_id"}),
 * @ORM\Index(name="event_program_title", columns={"event_program_id", "event_title"}),
 *     },
 *     options={"engine":"InnoDB"}
 * )
 * @ORM\HasLifecycleCallbacks()
 */
class Event {
	/**
	 * Available metrics type, hard-coded here for accessibility,
	 * while the logic to compute these stats lives in EventProcessor.
	 *
	 * Keys are i18n message keys, values are the 'offset' values.
	 *
	 * @see EventProcessor
	 * @see EventStat
	 */
	public const AVAILABLE_METRICS = [
		'participants' => null,
		'new-editors' => 14,
		'retention' => 7,
		'edits' => null,
		'byte-difference' => null,
		'pages-created' => null,
		'pages-improved' => null,
		'pages-created-pageviews' => null,
		'pages-improved-pageviews-avg' => 30,
		'files-uploaded' => null,
		'file-usage' => null,
		'pages-using-files' => null,
		'pages-using-files-pageviews-avg' => 30,
		'items-created' => null,
		'items-improved' => null,
	];

	private const NORMAL_WIKI_METRICS = [
		'pages-created',
		'pages-improved',
		'byte-difference',
		'pages-created-pageviews',
		'pages-improved-pageviews-avg',
		'files-uploaded',
		'file-usage',
		'pages-using-files',
		'pages-using-files-pageviews-avg',
	];

	/**
	 * This defines what metrics are available to what wiki families. '*' means all wikis are applicable.
	 */
	public const WIKI_FAMILY_METRIC_MAP = [
		'*' => [ 'edits', 'participants', 'new-editors', 'retention' ],
		'wikipedia' => self::NORMAL_WIKI_METRICS,
		'wiktionary' => self::NORMAL_WIKI_METRICS,
		'wikivoyage' => self::NORMAL_WIKI_METRICS,
		'commons' => [
			'files-uploaded',
			'file-usage',
			'pages-using-files',
			'pages-using-files-pageviews-avg',
		],
		'wikidata' => [ 'items-created', 'items-improved' ],
	];

	/**
	 * This defines what metrics are visible throughout the application,
	 * except for reports (which custom define what they include).
	 * The order specified here is also the order it will appear in the interface.
	 */
	public const VISIBLE_METRICS = [
		'participants',
		'new-editors',
		'retention',
		'pages-created',
		'pages-improved',
		'files-uploaded',
		'file-usage',
		'items-created',
		'items-improved',
	];

	/**
	 * NOTE: Some methods pertaining to titles and Participants live in the TitleUserTrait trait.
	 */
	use TitleUserTrait;

	/**
	 * Used purely to move out some of the logic to a dedicated file.
	 */
	use EventStatTrait;

	/**
	 * @ORM\Id
	 * @ORM\Column(name="event_id", type="integer")
	 * @ORM\GeneratedValue
	 * @var int|null Unique ID of the event.
	 */
	protected ?int $id = null;

	/**
	 * Many Events have one Program.
	 * @ORM\ManyToOne(targetEntity="Program", inversedBy="events")
	 * @ORM\JoinColumn(name="event_program_id", referencedColumnName="program_id", nullable=false)
	 * @var Program Program to which this event belongs.
	 */
	protected Program $program;

	/**
	 * One Event has many Participants.
	 * @ORM\OneToMany(
	 *     targetEntity="Participant", mappedBy="event", orphanRemoval=true, cascade={"persist"}, fetch="EXTRA_LAZY"
	 * )
	 * @var Collection<Participant> Participants of this Event.
	 */
	protected Collection $participants;

	/**
	 * One Event has many EventStats.
	 * @ORM\OneToMany(targetEntity="EventStat", mappedBy="event", orphanRemoval=true)
	 * @var Collection<EventStat> Statistics of this Event.
	 */
	protected Collection $stats;

	/**
	 * One Event has many EventWikis.
	 * @ORM\OneToMany(targetEntity="EventWiki", mappedBy="event", orphanRemoval=true, cascade={"persist"})
	 * @ORM\OrderBy({"domain" = "ASC"})
	 * @var Collection<EventWiki> Wikis that this event takes place on.
	 */
	protected Collection $wikis;

	/**
	 * One Event has many EventCategories.
	 * @ORM\OneToMany(targetEntity="EventCategory", mappedBy="event", orphanRemoval=true, cascade={"persist"})
	 * @var Collection<EventCategory> Categories that this event takes place on.
	 */
	protected Collection $categories;

	/**
	 * @ORM\Column(name="event_title", type="string", length=255)
	 * @Assert\Type("string")
	 * @Assert\Length(max=255)
	 * @var string|null The title of the event.
	 */
	protected ?string $title;

	/**
	 * @ORM\Column(name="event_start", type="datetime", nullable=false)
	 * @Assert\Type("\DateTimeInterface", message="error-invalid", payload={"0"="start-date"})
	 * @Assert\NotNull(message="error-invalid", payload={"0"="start-date"})
	 * @var DateTime|null The start date and time of the event.
	 */
	protected ?DateTime $start;

	/**
	 * @ORM\Column(name="event_end", type="datetime", nullable=false)
	 * @Assert\Type("\DateTimeInterface", message="error-invalid", payload={"0"="end-date"})
	 * @Assert\NotNull(message="error-invalid", payload={"0"="end-date"})
	 * @var DateTime|null The end date and time of the event.
	 */
	protected ?DateTime $end;

	/**
	 * @ORM\Column(
	 *     name="event_timezone",
	 *     type="string",
	 *     length=64,
	 *     options={"default":"UTC"}
	 * )
	 * @var string|null The timezone of the Event. Should be a PHP-supported timezone.
	 * @see https://secure.php.net/manual/en/timezones.php
	 */
	protected ?string $timezone;

	/**
	 * @ORM\Column(name="event_updated_at", type="datetime", nullable=true)
	 * @var DateTime|null The last time statistics were updated for this event.
	 */
	protected ?DateTime $updated;

	/**
	 * One Event has many Jobs.
	 * @ORM\OneToMany(targetEntity="Job", mappedBy="event", orphanRemoval=true)
	 * @var Collection<Job> Jobs for this Event.
	 */
	protected Collection $jobs;

	/**
	 * Event constructor.
	 * @param Program $program Program that this event belongs to.
	 * @param string|null $title Title of the event. This should be unique for the program.
	 * @param null $start Start date of the event.
	 * @param null $end End date of the event.
	 * @param string $timezone Official timezone code within the tz database.
	 */
	public function __construct(
		Program $program,
		?string $title = null,
				$start = null,
				$end = null,
		string $timezone = 'UTC'
	) {
		$this->program = $program;
		$this->setTitle( $title );
		$this->setTimezone( $timezone );
		$this->assignDate( 'start', $start );
		$this->assignDate( 'end', $end );

		$this->participants = new ArrayCollection();
		$this->stats = new ArrayCollection();
		$this->wikis = new ArrayCollection();
		$this->categories = new ArrayCollection();
		$this->jobs = new ArrayCollection();
	}

	/**
	 * The class name of users associated with Events.
	 * This is referenced in TitleUserTrait.
	 * @see TitleUserTrait
	 * @return string
	 */
	public function getUserClassName(): string {
		return 'Participant';
	}

	/**
	 * Get the ID of the event.
	 * @return int|null
	 */
	public function getId(): ?int {
		return $this->id;
	}

	/**
	 * Get unique cache key for the Event. This is called by Repository::getCacheKey(),
	 * used when making expensive queries against the replicas.
	 * @return string
	 */
	public function getCacheKey(): string {
		return (string)$this->id;
	}

	/**
	 * Is the Event valid? If false, statistics will not be able to be generated.
	 * @return bool
	 */
	public function isValid(): bool {
		return $this->wikis->count() > 0 &&
			$this->start !== null &&
			$this->end !== null &&
			$this->getStartUTC() < new DateTime() &&
			(
				$this->participants->count() > 0 ||
				$this->getNumCategories( true ) > 0
			);
	}

	/***********
	 * PROGRAM *
	 ***********/

	/**
	 * Get the Program associated with this Event.
	 * @return Program
	 */
	public function getProgram(): Program {
		return $this->program;
	}

	/*********
	 * DATES *
	 *********/

	/**
	 * Get the start date of this Event.
	 * @see self::getStartUTC() if you need to use the datestamp in an SQL query.
	 * @return DateTime|null
	 */
	public function getStart(): ?DateTime {
		return $this->start;
	}

	/**
	 * Set the start date of this Event.
	 * @param DateTime|string|null $value
	 */
	public function setStart( DateTime|string|null $value ): void {
		$this->assignDate( 'start', $value );
	}

	/**
	 * Get the start date in UTC. This is what should be used in SQL queries.
	 * @return DateTime
	 */
	public function getStartUTC(): DateTime {
		$dateStr = $this->start->format( 'YmdHis' );
		$dt = new DateTime( $dateStr, new DateTimeZone( $this->timezone ) );
		$dt->setTimezone( new DateTimeZone( 'UTC' ) );
		return $dt;
	}

	/**
	 * Get the end date of this Event.
	 * @see self::getEndUTC() if you need to use the datestamp in an SQL query.
	 * @return DateTime|null
	 */
	public function getEnd(): ?DateTime {
		return $this->end;
	}

	/**
	 * Get the end date in UTC. This is what should be used in SQL queries.
	 * @return DateTime
	 */
	public function getEndUTC(): DateTime {
		$dateStr = $this->end->format( 'YmdHis' );
		$dt = new DateTime( $dateStr, new DateTimeZone( $this->timezone ) );
		$dt->setTimezone( new DateTimeZone( 'UTC' ) );
		return $dt;
	}

	/**
	 * Set the end date of this Event.
	 * @param DateTime|string|null $value
	 */
	public function setEnd( DateTime|string|null $value ): void {
		$this->assignDate( 'end', $value );
	}

	/**
	 * Convert the given date argument to a DateTime and save to class property.
	 * @param string $key 'start' or 'end'.
	 * @param DateTime|string|null $value
	 */
	private function assignDate( string $key, DateTime|string|null $value ): void {
		if ( $value instanceof DateTime ) {
			$this->{$key} = $value;
		} elseif ( is_string( $value ) ) {
			$this->{$key} = new DateTime(
				$value,
				new DateTimeZone( 'UTC' )
			);
		} else {
			$this->{$key} = null;
		}
	}

	/**
	 * Get the end date of this Event.
	 * @return string
	 */
	public function getTimezone(): string {
		return $this->timezone;
	}

	/**
	 * Get the display variant of the timezone.
	 * @return string
	 */
	public function getDisplayTimezone(): string {
		return str_replace( '_', ' ', $this->timezone );
	}

	/**
	 * Get the end date of this Event.
	 * @param string $timezone Official timezone code within the tz database.
	 */
	public function setTimezone( string $timezone ): void {
		$this->timezone = $timezone;
	}

	/**************
	 * STATISTICS *
	 **************/

	// @see EventStatTrait

	/**************
	 * CATEGORIES *
	 **************/

	/**
	 * Get categories belonging to this Event.
	 * @return Collection<EventCategory>
	 */
	public function getCategories(): Collection {
		return $this->categories;
	}

	/**
	 * Get the number of categories belonging to this Event.
	 * @param bool $saved Whether to only count saved categories (have an ID).
	 * @return int
	 */
	public function getNumCategories( bool $saved = false ): int {
		if ( $saved === false ) {
			return $this->categories->count();
		}

		return $this->categories->filter( static function ( EventCategory $category ) {
			return $category->getId() !== null;
		} )->count();
	}

	/**
	 * Get the titles of categories belonging to this Event that are for the specified wiki.
	 * @param EventWiki $wiki
	 * @return string[]
	 */
	public function getCategoryTitlesForWiki( EventWiki $wiki ): array {
		return $this->getCategoriesForWiki( $wiki )->map( static function ( EventCategory $category ) {
			return $category->getTitle( true );
		} )->toArray();
	}

	/**
	 * Get the IDs (in replica database) of categories belonging to this Event that are the specified wiki.
	 * @param EventWiki $wiki
	 * @return int[]
	 */
	public function getCategoryIdsForWiki( EventWiki $wiki ): array {
		return $this->getCategoriesForWiki( $wiki )->map( static function ( EventCategory $category ) {
			return $category->getCategoryId();
		} )->toArray();
	}

	/**
	 * Get categories belonging to this Event that are for the specified wiki.
	 * @param EventWiki $wiki
	 * @return Collection<EventCategory>
	 */
	public function getCategoriesForWiki( EventWiki $wiki ): Collection {
		return $this->categories->filter( static function ( EventCategory $category ) use ( $wiki ) {
			return $category->getDomain() === $wiki->getDomain();
		} );
	}

	/**
	 * Add an EventCategory to the Event.
	 * @param EventCategory $category
	 */
	public function addCategory( EventCategory $category ): void {
		if ( $this->categories->contains( $category ) ) {
			return;
		}
		$this->categories->add( $category );
	}

	/**
	 * Remove an EventCategory from the Event.
	 * @param EventCategory $category
	 */
	public function removeCategory( EventCategory $category ): void {
		if ( !$this->categories->contains( $category ) ) {
			return;
		}
		$this->categories->removeElement( $category );
	}

	/**
	 * Remove all categories.
	 */
	public function clearCategories(): void {
		$this->categories->clear();
	}

	/**
	 * Before flushing to the database, remove categories for which no relevant EventWiki exists.
	 * This can happen when removing a wiki from an Event after you had an EventCategory created for the same wiki.
	 * @ORM\PreFlush()
	 */
	public function removeInvalidCategories(): void {
		foreach ( $this->categories->getIterator() as $category ) {
			if ( preg_match( $this->getAvailableWikiPattern(), $category->getDomain() ) !== 1 ) {
				$this->removeCategory( $category );
			}
		}
	}

	/****************
	 * PARTICIPANTS *
	 ****************/

	/**
	 * Get participants of this Event.
	 * @return Collection<Participant>
	 */
	public function getParticipants(): Collection {
		return $this->participants;
	}

	/**
	 * Get the number of participants of this Event.
	 * @return int
	 */
	public function getNumParticipants(): int {
		// Use the derived participant count if available, otherwise raw count of Participant objects.
		// This is to accommodate events with no explicit participants entered (e.g. only a category).
		$parStat = $this->getStatistic( 'participants' );
		return $parStat && $parStat->getValue() !== null ?
			(int)$parStat->getValue() :
			$this->participants->count();
	}

	/**
	 * Add an Participant to this Event.
	 * @param Participant $participant
	 */
	public function addParticipant( Participant $participant ): void {
		if ( $this->participants->contains( $participant ) ) {
			return;
		}
		$this->participants->add( $participant );
	}

	/**
	 * Remove a Participant from this Event.
	 * @param Participant $participant
	 */
	public function removeParticipant( Participant $participant ): void {
		if ( !$this->participants->contains( $participant ) ) {
			return;
		}
		$this->participants->removeElement( $participant );
	}

	/**
	 * Get the user IDs of all the Participants of this Event.
	 * @return int[]
	 */
	public function getParticipantIds(): array {
		return $this->participants->map( static function ( Participant $participant ) {
			return $participant->getUserId();
		} )->toArray();
	}

	/**
	 * Get the usernames of the Participants of this Event.
	 * @return string[]
	 */
	public function getParticipantNames(): array {
		return $this->participants->map( static function ( Participant $participant ) {
			return $participant->getUsername();
		} )->toArray();
	}

	/**
	 * Remove all Participants.
	 */
	public function clearParticipants(): void {
		$this->participants->clear();
	}

	/********
	 * WIKI *
	 ********/

	/**
	 * Get wikis this event is taking place on.
	 * @return Collection<EventWiki>
	 */
	public function getWikis(): Collection {
		return $this->wikis;
	}

	/**
	 * Get all of this event's wikis that do not yet have at least one category or (in the case of Wikidata) participant
	 * defined.
	 *
	 * @return Collection<EventWiki>
	 */
	public function getWikisWithoutFilters(): Collection {
		$wikis = $this->getWikisWithoutCategories();
		$wikidata = $this->getWikiByDomain( 'www.wikidata' );
		if ( $wikidata && $this->participants->count() === 0 ) {
			$wikis->add( $wikidata );
		}
		return $wikis;
	}

	/**
	 * Get all of this event's wikis that do not yet have at least one category defined.
	 * Wikidata is excluded because it can never have categories.
	 * @return Collection<EventWiki>
	 */
	public function getWikisWithoutCategories(): Collection {
		return $this->wikis->filter( function ( EventWiki $eventWiki ) {
			// Wikidata never has categories, so we don't return it in this list.
			if ( $eventWiki->getDomain() === 'www.wikidata' ) {
				return false;
			}
			foreach ( $this->categories as $cat ) {
				if ( $cat->getDomain() === $eventWiki->getDomain() ) {
					return false;
				}
			}
			return true;
		} );
	}

	/**
	 * Get the EventWiki with the given domain that belongs to this Event.
	 * @param string $domain
	 * @return EventWiki|false False if not found.
	 */
	public function getWikiByDomain( string $domain ): EventWiki|false {
		return $this->wikis->filter( static function ( EventWiki $wiki ) use ( $domain ) {
			return $wiki->getDomain() === $domain;
		} )->first();
	}

	/**
	 * Add an EventWiki to this Event.
	 * @param EventWiki $wiki
	 */
	public function addWiki( EventWiki $wiki ): void {
		if ( $this->wikis->contains( $wiki ) ) {
			return;
		}
		$this->wikis->add( $wiki );
	}

	/**
	 * Remove an EventWiki from this Event.
	 * @param EventWiki $wiki
	 */
	public function removeWiki( EventWiki $wiki ): void {
		if ( !$this->wikis->contains( $wiki ) ) {
			return;
		}
		$this->wikis->removeElement( $wiki );
	}

	/**
	 * Get the regex pattern for wikis defined on the Event.
	 * @return string
	 */
	public function getAvailableWikiPattern(): string {
		$regex = implode( '|', $this->getOrphanWikisAndFamilies()->map( static function ( EventWiki $wiki ) {
			// Regex-ify the domain name.
			return str_replace( '\*', '\w+', preg_quote( $wiki->getDomain() ) );
		} )->toArray() );

		return "/$regex/";
	}

	/***************
	 * WIKI FAMILY *
	 ***************/

	/**
	 * Get all EventWikis belonging to the Event that represent
	 * a wiki family (*.wikipedia, *.wiktionary, etc).
	 * @return Collection<EventWiki>
	 */
	public function getFamilyWikis(): Collection {
		return $this->wikis->filter( static function ( EventWiki $wiki ) {
			return str_starts_with( (string)$wiki->getDomain(), '*.' );
		} );
	}

	/**
	 * This method returns all EventWikis associated with the Event, grouped by the name of the associated family.
	 * It is used for display purposes on the Event page. This does not pay mind to whether there is an EventWiki
	 * representing a family (e.g. *.wikipedia). For instance, if there are EventWikis for en.wikipedia, fr.wikipedia,
	 * and commons.wikipedia, the two Wikipedias are grouped together. If there's also a *.wikipedia,
	 * it is not included in the 'wikipedia' group.
	 * @return EventWiki[]
	 */
	public function getWikisByFamily(): array {
		$wikisByFamily = [];

		foreach ( $this->wikis->getIterator() as $wiki ) {
			if ( $wiki->isFamilyWiki() ) {
				continue;
			}

			$familyName = $wiki->getFamilyName();
			if ( !isset( $wikisByFamily[$familyName] ) ) {
				$wikisByFamily[$familyName] = [ $wiki ];
			} else {
				$wikisByFamily[$familyName][] = $wiki;
			}
		}

		return $wikisByFamily;
	}

	/**
	 * Get all associated EventWikis that belong to a family.
	 * @return Collection<EventWiki>
	 */
	public function getChildWikis(): Collection {
		return $this->wikis->filter( static function ( EventWiki $wiki ) {
			return $wiki->isChildWiki();
		} );
	}

	/**
	 * Get all EventWikis that are not part of a family that have been added
	 * to the Event. For instance, if there is an EventWiki for *.wikipedia
	 * (wikipedia family), a fr.wikipedia EventWiki is not returned, but it
	 * will if there is not a *.wikipedia EventWiki.
	 * @return Collection<EventWiki>
	 */
	public function getOrphanWikis(): Collection {
		$familyNames = $this->getFamilyWikis()->map( static function ( EventWiki $eventWiki ) {
			return $eventWiki->getFamilyName();
		} );

		return $this->wikis->filter( static function ( EventWiki $wiki ) use ( $familyNames ) {
			return $wiki->getDomain() === null
				|| !$familyNames->contains( $wiki->getFamilyName() );
		} );
	}

	/**
	 * Remove all associated EventWikis that belong to a family.
	 */
	public function clearChildWikis(): void {
		$children = $this->getChildWikis()->toArray();
		foreach ( $children as $child ) {
			$this->removeWiki( $child );
		}
	}

	/**
	 * Get EventWikis that are represent a wiki family, or an individual wiki that is not part of a family.
	 * @return ArrayCollection<EventWiki>
	 */
	public function getOrphanWikisAndFamilies(): ArrayCollection {
		return new ArrayCollection( array_merge(
			$this->getFamilyWikis()->toArray(),
			$this->getOrphanWikis()->toArray()
		) );
	}

	/********
	 * JOBS *
	 ********/

	/**
	 * Add a Job for this Event.
	 * @param Job $job
	 */
	public function addJob( Job $job ): void {
		if ( $this->jobs->contains( $job ) ) {
			return;
		}
		$this->jobs->add( $job );
	}

	/**
	 * A convenience method to get the first Job (which should be the only one, for now).
	 * @return Job|false
	 */
	public function getJob(): Job|false {
		return $this->jobs->first();
	}

	/**
	 * Get jobs associated with this Event (in theory there should be only one).
	 * @return Collection<Job>
	 */
	public function getJobs(): Collection {
		return $this->jobs;
	}

	/**
	 * Get the number of jobs associated with this Event. (Ideally there'd only be one, but this is here just in case.)
	 * @return int
	 */
	public function getNumJobs(): int {
		return $this->jobs->count();
	}

	/**
	 * Is there a job associated with this Event?
	 * @return bool
	 */
	public function hasJob(): bool {
		return $this->getNumJobs() > 0;
	}

	/**
	 * Remove a Job from the Event. This does NOT kill the job if it is currently running.
	 * @param Job $job
	 */
	public function removeJob( Job $job ): void {
		if ( !$this->jobs->contains( $job ) ) {
			return;
		}
		$this->jobs->removeElement( $job );
	}

	/**
	 * Remove all Jobs from this Event.
	 */
	public function clearJobs(): void {
		$this->jobs->clear();
	}

	/**
	 * Get stale jobs that have been idling for a long time (specified by $offset).
	 * @param string $offset String accepted by DateTime constructor.
	 * @return Collection
	 */
	public function getStaleJobs( string $offset = '-1 hour' ): Collection {
		return $this->jobs->filter( static function ( Job $job ) use ( $offset ) {
			return $job->getSubmitted() <= new DateTime( $offset );
		} );
	}
}
