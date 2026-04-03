<?php
// Events to EventCommitments OneToMany relationship metadata
return [
    'name' => 'events_event_commitments',
    'type' => 'OneToMany',
    'modelOne' => 'Events',
    'modelMany' => 'EventCommitments',
    'constraints' => [],
    'additionalFields' => [],
];
