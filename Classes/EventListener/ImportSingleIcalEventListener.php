<?php

declare(strict_types=1);

namespace HDNET\Calendarize\EventListener;

use HDNET\Calendarize\Domain\Model\Event;
use HDNET\Calendarize\Domain\Repository\EventRepository;
use HDNET\Calendarize\Event\ImportSingleIcalEvent;
use HDNET\Calendarize\Ical\ICalEvent;
use HDNET\Calendarize\Service\EventConfigurationService;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Class ImportSingleIcalEventListener.
 */
class ImportSingleIcalEventListener
{
    /**
     * Event repository.
     *
     * @var EventRepository
     */
    private $eventRepository;

    /**
     * @var PersistenceManager
     */
    private $persistenceManager;

    /**
     * @var EventConfigurationService
     */
    private $eventConfigurationService;

    /**
     * ImportSingleIcalEventListener constructor.
     *
     * @param EventRepository           $eventRepository
     * @param PersistenceManager        $persistenceManager
     * @param EventConfigurationService $eventConfigurationService
     */
    public function __construct(
        EventRepository $eventRepository,
        PersistenceManager $persistenceManager,
        EventConfigurationService $eventConfigurationService
    ) {
        $this->eventRepository = $eventRepository;
        $this->persistenceManager = $persistenceManager;
        $this->eventConfigurationService = $eventConfigurationService;
    }

    public function __invoke(ImportSingleIcalEvent $event)
    {
        // TODO: Workaround to disable default event. Look for better solution!
        if ((bool)\HDNET\Calendarize\Utility\ConfigurationUtility::get('disableDefaultEvent')) {
            return;
        }

        $calEvent = $event->getEvent();
        $pid = $event->getPid();

        $importId = \strlen($calEvent->getUid()) <= 100 ? $calEvent->getUid() : md5($calEvent->getUid());
        $eventObj = $this->initializeEventRecord($importId,$pid);
        $this->hydrateEventRecord($eventObj, $calEvent, $pid);

        if (null !== $eventObj->getUid() && (int)$eventObj->getUid() > 0) {
            $this->eventRepository->update($eventObj);
        } else {
            $this->eventRepository->add($eventObj);
        }

        $this->persistenceManager->persistAll();
    }

    /**
     * Initializes or gets an event by import id.
     *
     * @param string $importId
     * @param int $pid
     *
     * @return Event
     */
    private function initializeEventRecord(string $importId,$pid): Event
    {
        $eventObj = $this->eventRepository->findOneByImportId($importId,$pid);

        if (!($eventObj instanceof Event)) {
            $eventObj = new Event();
            $eventObj->setImportId($importId);
        }

        return $eventObj;
    }

    /**
     * Hydrates the event record with the event data.
     *
     * @param Event     $eventObj
     * @param ICalEvent $calEvent
     * @param int       $pid
     */
    private function hydrateEventRecord(Event $eventObj, ICalEvent $calEvent, int $pid): void
    {
        $eventObj->setPid($pid);
        $eventObj->setTitle($calEvent->getTitle() ?? '');
        $eventObj->setDescription($calEvent->getDescription() ?? '');
        $eventObj->setLocation($calEvent->getLocation() ?? '');
        $eventObj->setOrganizer($calEvent->getOrganizer() ?? '');

        $importId = $eventObj->getImportId();

        $this->eventConfigurationService->hydrateCalendarize($eventObj->getCalendarize(), $calEvent, $importId, $pid);
    }
}
