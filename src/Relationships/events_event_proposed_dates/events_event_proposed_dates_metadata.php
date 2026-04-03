<?php
// Events to EventProposedDates OneToMany relationship metadata
return [
    'name' => 'events_event_proposed_dates',
    'type' => 'OneToMany',
    'modelOne' => 'Events',
    'modelMany' => 'EventProposedDates',
    'constraints' => [],
    'additionalFields' => [],
];
