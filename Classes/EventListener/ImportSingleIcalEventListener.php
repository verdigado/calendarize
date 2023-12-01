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

        $recurrence = $calEvent->getRawData()['RECURRENCE-ID'][0] ?? "";
        $recurrenceId = "";
        if (!empty($recurrence)) {
            $recurrenceId = preg_replace('#[^A-Za-z0-9=:\-/]#', '', $recurrence);
        }
        $importId = \strlen($calEvent->getUid() . $recurrenceId) <= 100 ? $calEvent->getUid().$recurrenceId : md5($calEvent->getUid().$recurrenceId);
        $eventObj = $this->initializeEventRecord($importId);
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
     *
     * @return Event
     */
    private function initializeEventRecord(string $importId): Event
    {
        $eventObj = $this->eventRepository->findOneByImportId($importId);

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
