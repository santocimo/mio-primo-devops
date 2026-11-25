export function getDeviceTimeZone(): string {
  const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  if (!timeZone) {
    throw new Error('The device timezone is unavailable.');
  }
  return timeZone;
}

export function localDateTimeToUtcIso(value: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(value);
  if (!match) {
    throw new Error('A valid local date and time are required.');
  }

  const [, yearText, monthText, dayText, hourText, minuteText, secondText = '0'] = match;
  const year = Number(yearText);
  const month = Number(monthText);
  const day = Number(dayText);
  const hour = Number(hourText);
  const minute = Number(minuteText);
  const second = Number(secondText);
  const localDate = new Date(year, month - 1, day, hour, minute, second);

  if (
    localDate.getFullYear() !== year ||
    localDate.getMonth() !== month - 1 ||
    localDate.getDate() !== day ||
    localDate.getHours() !== hour ||
    localDate.getMinutes() !== minute ||
    localDate.getSeconds() !== second
  ) {
    throw new Error('The selected local time does not exist.');
  }

  return localDate.toISOString();
}

export function apiDateTimeToLocalInput(value: string): string {
  if (!/(?:[zZ]|[+-]\d{2}:\d{2})$/.test(value)) {
    return value.replace(' ', 'T').substring(0, 16);
  }

  const instant = new Date(value);
  if (Number.isNaN(instant.getTime())) {
    throw new Error('The stored appointment timestamp is invalid.');
  }

  const pad = (part: number): string => String(part).padStart(2, '0');
  return `${instant.getFullYear()}-${pad(instant.getMonth() + 1)}-${pad(instant.getDate())}T${pad(instant.getHours())}:${pad(instant.getMinutes())}`;
}

export function formatDateTimeInTimeZone(
  value: string,
  timeZone: string,
  language: string,
  includeSeconds = false
): string {
  const instant = new Date(value);
  if (Number.isNaN(instant.getTime())) {
    throw new Error('The timestamp is invalid.');
  }

  const isEnglish = language === 'en';
  return new Intl.DateTimeFormat(isEnglish ? 'en-US' : 'it-IT', {
    timeZone,
    year: 'numeric',
    month: isEnglish ? 'short' : '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    ...(includeSeconds ? { second: '2-digit' } : {}),
    hourCycle: isEnglish ? 'h12' : 'h23',
  }).format(instant);
}
