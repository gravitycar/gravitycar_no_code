<?php
// Events to EventReminders OneToMany relationship metadata
return [
    'name' => 'events_event_reminders',
    'type' => 'OneToMany',
    'modelOne' => 'Events',
    'modelMany' => 'EventReminders',
    'constraints' => [],
    'additionalFields' => [],
];
