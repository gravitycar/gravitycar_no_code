export interface TimeValue {
  hour: number;   // 1-12
  minute: number;  // 0-55 in steps of 5
  ampm: 'AM' | 'PM';
}

export const formatTimeValue = (t: TimeValue): string => {
  const mm = String(t.minute).padStart(2, '0');
  return `${t.hour}:${mm} ${t.ampm}`;
};

export const to24Hour = (t: TimeValue): { hours: number; minutes: number } => {
  let hours = t.hour;
  if (t.ampm === 'AM' && hours === 12) hours = 0;
  if (t.ampm === 'PM' && hours !== 12) hours += 12;
  return { hours, minutes: t.minute };
};
