/**
 * D&D-themed loading quotes
 * 
 * These humorous quotes are displayed while waiting for the D&D RAG Chat server
 * to process a query. They cycle every 8 seconds (5s display + 3s fade).
 */

/**
 * Array of D&D-themed loading quotes
 */
export const dndQuotes: string[] = [
  "Girding our loins",
  "Gathering the party",
  "Mapping the catacombs",
  "Memorizing spells",
  "Collecting loot",
  "Saddling the pegasi",
  "Feeding the manticore",
  "Brandishing our blades",
  "Remembering to pack torches and rope",
  "Looking for our lockpicks",
  "Consulting the Dungeon Master's screen",
  "Sharpening our daggers of warning",
  "Rolling for initiative… slowly",
  "Checking for traps (again)",
  "Negotiating with the innkeeper",
  "Gambling in the tavern",
  "Plumbing the depths",
  "Travelling the planes",
  "Calming the owlbear",
  "Copying spells into the grimoire",
  "Silencing the shrieker",
  "Reprimanding the mimic",
  "Deciphering ancient runes",
  "Whispering to the familiars",
  "Summoning the Rules Lawyer",
  "Arguing with the Dungeon Master about line of sight",
  "Converting gold pieces to experience points",
  "Beholding the beholder",
  "Polishing the dragon's hoard (carefully)",
  "Traversing the Underdark",
  "Listening at doors",
  "Making our saving throw",
  "Ugh, Rot-grubs!"
];

/**
 * Get a random quote from the list, optionally excluding a specific index
 * @param excludeIndex Index to exclude (prevents consecutive repeats)
 * @returns Object containing the quote text and its index
 */
export function getRandomQuote(excludeIndex?: number): { quote: string; index: number } {
  let availableIndices = Array.from({ length: dndQuotes.length }, (_, i) => i);
  
  // Remove excluded index if provided and array has more than one quote
  if (excludeIndex !== undefined && dndQuotes.length > 1) {
    availableIndices = availableIndices.filter(i => i !== excludeIndex);
  }
  
  // Select random index from available indices
  const randomIndex = availableIndices[Math.floor(Math.random() * availableIndices.length)];
  
  return {
    quote: dndQuotes[randomIndex],
    index: randomIndex
  };
}
