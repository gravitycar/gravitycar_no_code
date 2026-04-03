import { CustomPage } from '../types/navigation';

export interface GroupedCustomPage extends CustomPage {
  children?: CustomPage[];
}

/**
 * Group custom pages by key prefix convention.
 * Items whose key contains an underscore are grouped as children
 * of the item whose key matches the prefix before the first underscore.
 * For example, "events_create" and "events_list" become children of "events".
 */
export function groupCustomPages(pages: CustomPage[]): GroupedCustomPage[] {
  const grouped: GroupedCustomPage[] = [];
  const childMap = new Map<string, CustomPage[]>();

  for (const page of pages) {
    const underscoreIndex = page.key.indexOf('_');
    if (underscoreIndex > 0) {
      const parentKey = page.key.substring(0, underscoreIndex);
      if (!childMap.has(parentKey)) {
        childMap.set(parentKey, []);
      }
      childMap.get(parentKey)!.push(page);
    } else {
      grouped.push({ ...page });
    }
  }

  for (const item of grouped) {
    const children = childMap.get(item.key);
    if (children) {
      item.children = children;
    }
  }

  return grouped;
}
