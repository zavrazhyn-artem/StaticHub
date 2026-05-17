// For Blizzard import/export strings
const base64Table = [
 "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O",
  "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "a", "b", "c", "d",
  "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s",
  "t", "u", "v", "w", "x", "y", "z", "0", "1", "2", "3", "4", "5", "6", "7",
  "8", "9", "+", "/"
];

// For our own internal URL hashes
const hashBase64Table = [
 "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O",
  "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "a", "b", "c", "d",
  "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s",
  "t", "u", "v", "w", "x", "y", "z", "0", "1", "2", "3", "4", "5", "6", "7",
  "8", "9", "+", ":"
];

class BinaryArrayReader {

  /**
   * Creates an instance of BinaryArrayReader.
   * 
   * @param {string} string The input string to be converted.
   * @param {Array} hash An array representing the hash used to convert characters to binary.
   */
  constructor(string, hash) {
    this.array = [];
    for (var i = 0; i < string.length; i++) {
      let value = hash.indexOf(string[i]);
      // Base 64 is a 6-bit encoding, because all numerical values are between 0 and 63 (each represented by a readable character)
      for (var j = 0; j < 6; j++) {
        this.array.push(value >> j & 1);
      }
    }
    this.arrayIndex = 0;
  }

  read(width) {
    if (this.arrayIndex + width > this.array.length) {
      return -1;
    }
    let result = 0;
    for (var i = 0; i < width; i++) {
      result += this.array[this.arrayIndex + i] << i;
    }
    this.arrayIndex += width;
    return result;
  }

  bitsRemaining() {
    if (this.arrayIndex >= this.array.length) {
      return 0;
    } else {
      return this.array.length - this.arrayIndex;
    }
  }
}

class BinaryArrayWriter {

  constructor(hash) {
    this.hash = hash
    this.array = [];
  }

  // Example
  // C0QA encodes 2 on 8 bit (Serialization version) and 269 on 16 bits (Windwalker Monk)
  // C = 2
  // 0 = 52
  // Q = 16
  // A = 0
  //
  // 2 on 8 bit = 00000010 -> 01000000
  //
  // 269 on 16 bit = 0000000100001101 -> 1011000010000000
  // stream = 010000 (2) 001011 (52) 000010 (16) 000000 (0)
  write(value, width) {
    for (var i = 0; i < width; i++) {
      this.array.push(value >> i & 1);
    }
  }

  toExportString() {
    let string = "";
   
    // Keep adding 0 until array size is a multiple of 6
    while (this.array.length % 6 != 0) {
      this.array.push(0);
    }

    for (var i = 0; i < this.array.length; i += 6) {
      // Computing the value represented by the array of bits
      string += this.hash[this.array[i] * 1 + this.array[i+1] * 2 + this.array[i+2] * 4 + this.array[i+3] * 8 + this.array[i+4] * 16 + this.array[i+5] * 32];
    }

    return string;
  }
}

const MIDNIGHTDEBUG = false;

/**
 * @typedef {MidnightTalentCalculatorNodeChoice} ChoiceNode
 * @typedef {MidnightTalentCalculatorNodeRound} RoundNode
 * @typedef {MidnightTalentCalculatorNodeSquare} SquareNode
 * @typedef {MidnightTalentCalculatorClassTree} ClassTree
 * @typedef {MidnightTalentCalculatorSpecTree} SpecTree
 * @typedef {MidnightTalentCalculatorHeroTree} HeroTree
 */

class MidnightTalentCalculatorJSON {
  static version = 46;
  static hash = {}
  static jsonPath = "/proxy/icy-veins/json/midnight-talent-calculator";

  /**
   * Fetches JSON data from a source.
   * 
   * @param {string} slug Slug of the JSON file to be fetched.
   * @returns {Promise<Object>|Promise<Array>|Array|Object} A promise that resolves to the parsed JSON data
   * if newly fetched or the existing data from the hash.
   */
  static async get(slug) {
    if (!this.hash[slug]) {
      let response = await fetch(this.jsonPath + "/" + slug + ".json?v=" + this.version);
      let json = await response.json();

      this.hash[slug] = json;
      return json;
    } else {
      return this.hash[slug];
    }
  }
}

class MidnightTalentCalculatorSpriteHelper {
  static gameVersion = 'live';
  
  /**
   * Returns the spritesheet for the hero talents available for the given class.
   * 
   * @param {string} className Name of the class whose hero talent sprites we want.
   * @returns {string} The source url for the spritesheet.
   */
  static heroSprite(className) {
    switch (this.gameVersion) {
      case 'live':
        switch (className) {
          case 'death_knight':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-death_knight-hero-sprite-7baa22c0bacc9a20a5950efdea0fcf26.webp";
          case 'demon_hunter':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-demon_hunter-hero-sprite-b8e9f57820eb35a56f9e298c88ea5d4f.webp";
          case 'druid':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-druid-hero-sprite-eb869a7262decfc8917e5f016676250a.webp";
          case 'evoker':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-evoker-hero-sprite-4abeb64a0e69ed173fd066801582f2ad.webp";
          case 'hunter':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-hunter-hero-sprite-cd1774b970d4521487861697e9cd284e.webp";
          case 'mage':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-mage-hero-sprite-cdbb09db760ff7c82b9413efd8b6f122.webp";
          case 'monk':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-monk-hero-sprite-4645ac61d02b7b58c1701b95b362f6f6.webp";
          case 'paladin':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-paladin-hero-sprite-578abbfe941477937255aa797088d3a5.webp";
          case 'priest':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-priest-hero-sprite-e1e946bf77662fdf1cae257d92374142.webp";
          case 'rogue':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-rogue-hero-sprite-6c0fb78549d8bb98023383efd8e7ef75.webp";
          case 'shaman':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-shaman-hero-sprite-429e6a5d45297febb8982845e16c3ac4.webp";
          case 'warlock':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-warlock-hero-sprite-f46f534137b71bfd415db54201988c47.webp";
          case 'warrior':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-warrior-hero-sprite-5eba490e25465aead3037cb7465a7402.webp";
          default:
            console.log("Unknown className in heroSprite live: " + className);
            break;
        }
      case 'ptr':
        switch (className) {
          case 'death_knight':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-death_knight-hero-sprite-7baa22c0bacc9a20a5950efdea0fcf26.webp";
          case 'demon_hunter':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-demon_hunter-hero-sprite-b8e9f57820eb35a56f9e298c88ea5d4f.webp";
          case 'druid':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-druid-hero-sprite-eb869a7262decfc8917e5f016676250a.webp";
          case 'evoker':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-evoker-hero-sprite-4abeb64a0e69ed173fd066801582f2ad.webp";
          case 'hunter':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-hunter-hero-sprite-cd1774b970d4521487861697e9cd284e.webp";
          case 'mage':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-mage-hero-sprite-cdbb09db760ff7c82b9413efd8b6f122.webp";
          case 'monk':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-monk-hero-sprite-4645ac61d02b7b58c1701b95b362f6f6.webp";
          case 'paladin':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-paladin-hero-sprite-578abbfe941477937255aa797088d3a5.webp";
          case 'priest':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-priest-hero-sprite-e1e946bf77662fdf1cae257d92374142.webp";
          case 'rogue':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-rogue-hero-sprite-6c0fb78549d8bb98023383efd8e7ef75.webp";
          case 'shaman':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-shaman-hero-sprite-429e6a5d45297febb8982845e16c3ac4.webp";
          case 'warlock':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-warlock-hero-sprite-f46f534137b71bfd415db54201988c47.webp";
          case 'warrior':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-warrior-hero-sprite-5eba490e25465aead3037cb7465a7402.webp";
          default:
            console.log("Unknown className in heroSprite ptr: " + className);
            break;
        }
      default:
        console.log("Unknown game version: " + this.gameVersion);
    }
  }

  /**
   * Returns the spritesheet for the class and spec tree.
   * 
   * @param {string} className Name of the class whose sprites we need.
   * @param {string} specName Name of the spec whose sprites we need.
   * @returns {string} The source url for the spritesheet.
   */
  static specSprite(className, specName) {
    switch (this.gameVersion) {
      case 'live':
        switch (`${className}-${specName}`) {
          case 'death_knight-blood':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-death_knight-blood-sprite-fa2201edb9720733fbf7fdae0c22cd61.webp";
          case 'death_knight-frost':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-death_knight-frost-sprite-c8ddf20bcbfa43893098ffcc7e418289.webp";
          case 'death_knight-unholy':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-death_knight-unholy-sprite-825a739f15b8b6ed2aa9788d56a0402e.webp";
          case 'demon_hunter-devourer':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-demon_hunter-devourer-sprite-74423f626e2fc43e4129c8db8d339b8b.webp";
          case 'demon_hunter-havoc':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-demon_hunter-havoc-sprite-d10d5aadf4a303585f0e097af981597a.webp";
          case 'demon_hunter-vengeance':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-demon_hunter-vengeance-sprite-c0dc7cac031722203b50f9c0d81b5497.webp";
          case 'druid-balance':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-druid-balance-sprite-4a22fb4a67eaecad6a90217ff5ba8d99.webp";
          case 'druid-feral':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-druid-feral-sprite-d0590c6b7a3a27b294756720b7cd3562.webp";
          case 'druid-guardian':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-druid-guardian-sprite-25293abbe3035d2341e5952b5bb47765.webp";
          case 'druid-restoration':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-druid-restoration-sprite-ac5aee1d0bc7942054f4725abbd759e7.webp";
          case 'evoker-augmentation':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-evoker-augmentation-sprite-935361bd63059e50fd846933b47f0c5b.webp";
          case 'evoker-devastation':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-evoker-devastation-sprite-0675cf73da9844c7916f65692d3c797f.webp";
          case 'evoker-preservation':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-evoker-preservation-sprite-d832ac7caa7be0b4c5c993dfa224b3d0.webp";
          case 'hunter-beast-mastery':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-hunter-beast-mastery-sprite-f6cc8ceeab36566e103474c3be763bb0.webp";
          case 'hunter-marksmanship':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-hunter-marksmanship-sprite-75e7cefce4e8d7e3c53f8c80214f201f.webp";
          case 'hunter-survival':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-hunter-survival-sprite-93d6bfda5e0864e547d6ac6fb46fe742.webp";
          case 'mage-arcane':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-mage-arcane-sprite-ae126a87b0ec13c6a8b404bd1b0df88c.webp";
          case 'mage-fire':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-mage-fire-sprite-51d84f4f753d72d47df3fa82500ec728.webp";
          case 'mage-frost':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-mage-frost-sprite-599c1cd3f4cdbc5ce02917cd22990f1d.webp";
          case 'monk-brewmaster':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-monk-brewmaster-sprite-2279cb96e074e482b5f8a1e2bd2cef2b.webp";
          case 'monk-mistweaver':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-monk-mistweaver-sprite-3f48ca7979d6b81d61c2d3b9f1a8e6e4.webp";
          case 'monk-windwalker':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-monk-windwalker-sprite-bd8850f0299c835554cd33280a4abb70.webp";
          case 'paladin-holy':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-paladin-holy-sprite-10b4844cef7c147160fe879ccf6300ef.webp";
          case 'paladin-protection':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-paladin-protection-sprite-530eb317b4c2471174b7424b37efc693.webp";
          case 'paladin-retribution':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-paladin-retribution-sprite-0fbe58186209729383bcf700fb06cff3.webp";
          case 'priest-discipline':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-priest-discipline-sprite-a98ae7bcb94f146d31b13dbb140383da.webp";
          case 'priest-holy':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-priest-holy-sprite-d31622f9037a0353326fe991cedbbac8.webp";
          case 'priest-shadow':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-priest-shadow-sprite-f802ab603a57ea6c6548119dcc722165.webp";
          case 'rogue-assassination':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-rogue-assassination-sprite-2031272effdd1f52e56094b8f53b2a2c.webp";
          case 'rogue-outlaw':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-rogue-outlaw-sprite-4e88ac07f72e6eb688366de64e89e3d8.webp";
          case 'rogue-subtlety':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-rogue-subtlety-sprite-0f1ddf1f5cf67a48f2f2a4362768d302.webp";
          case 'shaman-elemental':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-shaman-elemental-sprite-47d3d74f1114d8aa0d6ab314d2b7cead.webp";
          case 'shaman-enhancement':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-shaman-enhancement-sprite-7b438e33cc48158eed82dfcd77ac014e.webp";
          case 'shaman-restoration':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-shaman-restoration-sprite-509afe8285e97134adfeeef052f63786.webp";
          case 'warlock-affliction':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-warlock-affliction-sprite-1e08bbbb6de8dc3c41a8ccb2ff6f5f99.webp";
          case 'warlock-demonology':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-warlock-demonology-sprite-8572a09229948f988ef5a215d807569b.webp";
          case 'warlock-destruction':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-warlock-destruction-sprite-aa439335ebce4754a3ed0d67f6423438.webp";
          case 'warrior-arms':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-warrior-arms-sprite-fc13ceffcbe7df84618ab5a1d80b3e8e.webp";
          case 'warrior-fury':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-warrior-fury-sprite-40524e1592d3c19fe7d9d1181d7f290b.webp";
          case 'warrior-protection':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-warrior-protection-sprite-ddbde781ae2c3f9f44ca1004ec9850ff.webp";
          default:
            console.log(`Unknown [className, specName] in specSprite live: [${className}, ${specName}]`);
        }
      case 'ptr':
        switch (`${className}-${specName}`) {
          case 'death_knight-blood':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-death_knight-blood-sprite-fa2201edb9720733fbf7fdae0c22cd61.webp";
          case 'death_knight-frost':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-death_knight-frost-sprite-c8ddf20bcbfa43893098ffcc7e418289.webp";
          case 'death_knight-unholy':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-death_knight-unholy-sprite-825a739f15b8b6ed2aa9788d56a0402e.webp";
          case 'demon_hunter-devourer':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-demon_hunter-devourer-sprite-74423f626e2fc43e4129c8db8d339b8b.webp";
          case 'demon_hunter-havoc':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-demon_hunter-havoc-sprite-d10d5aadf4a303585f0e097af981597a.webp";
          case 'demon_hunter-vengeance':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-demon_hunter-vengeance-sprite-c0dc7cac031722203b50f9c0d81b5497.webp";
          case 'druid-balance':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-druid-balance-sprite-4a22fb4a67eaecad6a90217ff5ba8d99.webp";
          case 'druid-feral':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-druid-feral-sprite-d0590c6b7a3a27b294756720b7cd3562.webp";
          case 'druid-guardian':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-druid-guardian-sprite-25293abbe3035d2341e5952b5bb47765.webp";
          case 'druid-restoration':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-druid-restoration-sprite-ac5aee1d0bc7942054f4725abbd759e7.webp";
          case 'evoker-augmentation':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-evoker-augmentation-sprite-935361bd63059e50fd846933b47f0c5b.webp";
          case 'evoker-devastation':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-evoker-devastation-sprite-0675cf73da9844c7916f65692d3c797f.webp";
          case 'evoker-preservation':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-evoker-preservation-sprite-d832ac7caa7be0b4c5c993dfa224b3d0.webp";
          case 'hunter-beast-mastery':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-hunter-beast-mastery-sprite-f6cc8ceeab36566e103474c3be763bb0.webp";
          case 'hunter-marksmanship':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-hunter-marksmanship-sprite-75e7cefce4e8d7e3c53f8c80214f201f.webp";
          case 'hunter-survival':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-hunter-survival-sprite-93d6bfda5e0864e547d6ac6fb46fe742.webp";
          case 'mage-arcane':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-mage-arcane-sprite-ae126a87b0ec13c6a8b404bd1b0df88c.webp";
          case 'mage-fire':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-mage-fire-sprite-51d84f4f753d72d47df3fa82500ec728.webp";
          case 'mage-frost':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-mage-frost-sprite-599c1cd3f4cdbc5ce02917cd22990f1d.webp";
          case 'monk-brewmaster':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-monk-brewmaster-sprite-2279cb96e074e482b5f8a1e2bd2cef2b.webp";
          case 'monk-mistweaver':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-monk-mistweaver-sprite-3f48ca7979d6b81d61c2d3b9f1a8e6e4.webp";
          case 'monk-windwalker':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-monk-windwalker-sprite-bd8850f0299c835554cd33280a4abb70.webp";
          case 'paladin-holy':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-paladin-holy-sprite-10b4844cef7c147160fe879ccf6300ef.webp";
          case 'paladin-protection':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-paladin-protection-sprite-530eb317b4c2471174b7424b37efc693.webp";
          case 'paladin-retribution':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-paladin-retribution-sprite-0fbe58186209729383bcf700fb06cff3.webp";
          case 'priest-discipline':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-priest-discipline-sprite-a98ae7bcb94f146d31b13dbb140383da.webp";
          case 'priest-holy':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-priest-holy-sprite-d31622f9037a0353326fe991cedbbac8.webp";
          case 'priest-shadow':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-priest-shadow-sprite-f802ab603a57ea6c6548119dcc722165.webp";
          case 'rogue-assassination':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-rogue-assassination-sprite-2031272effdd1f52e56094b8f53b2a2c.webp";
          case 'rogue-outlaw':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-rogue-outlaw-sprite-4e88ac07f72e6eb688366de64e89e3d8.webp";
          case 'rogue-subtlety':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-rogue-subtlety-sprite-0f1ddf1f5cf67a48f2f2a4362768d302.webp";
          case 'shaman-elemental':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-shaman-elemental-sprite-47d3d74f1114d8aa0d6ab314d2b7cead.webp";
          case 'shaman-enhancement':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-shaman-enhancement-sprite-7b438e33cc48158eed82dfcd77ac014e.webp";
          case 'shaman-restoration':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-shaman-restoration-sprite-509afe8285e97134adfeeef052f63786.webp";
          case 'warlock-affliction':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-warlock-affliction-sprite-1e08bbbb6de8dc3c41a8ccb2ff6f5f99.webp";
          case 'warlock-demonology':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-warlock-demonology-sprite-8572a09229948f988ef5a215d807569b.webp";
          case 'warlock-destruction':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-warlock-destruction-sprite-aa439335ebce4754a3ed0d67f6423438.webp";
          case 'warrior-arms':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-warrior-arms-sprite-fc13ceffcbe7df84618ab5a1d80b3e8e.webp";
          case 'warrior-fury':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-warrior-fury-sprite-40524e1592d3c19fe7d9d1181d7f290b.webp";
          case 'warrior-protection':
            return "/proxy/icy-veins/sprites/midnight-talent-calculator-ptr-warrior-protection-sprite-ddbde781ae2c3f9f44ca1004ec9850ff.webp";
          default:
            console.log(`Unknown [className, specName] in specSprite ptr: [${className}, ${specName}]`);
        }
      default:
        console.log("Unknown game version: " + this.gameVersion);
    }
  }
}

class MidnightTalentCalculatorHistory {
  /**
   * Create tree history object.
   * 
   * @param {ClassTree|SpecTree|HeroTree} tree The tree whose history we want to record.
   */
  constructor(tree) {
    this.tree = tree;
    this.actions = [];
  }

  /**
   * Resets the history actions array.
   */
  reset() {
    this.actions = [];
  }

  /**
   * Adds an entry to the actions array when a round or square node is activated.
   * 
   * @param {number} nodeId The activated square or round node id.
   */
  addPointRoundSquareNode(nodeId) {
    this.actions.push({'nodeId': nodeId})
  }

  /**
   * Adds an entry to the actions array when a choice node is activated.
   * 
   * @param {ChoiceNode} node The activated choice node.
   * @param {Number} choice The chosen talent from the choice node popup.
   */
  addPointChoiceNode(node, choice) {
    this.actions.push({'nodeId': node.id, 'choice': choice})
  }

  /**
   * Updates an entry from the actions array when a choice node skill is changed.
   * 
   * @param {ChoiceNode} node The clicked on choice node.
   * @param {Number} choice The new chosen talent from the choice node popup.
   */
  updatePointChoiceNode(node, choice) {
    let choiceNode = node.subtree.history.actions.find((obj) => obj.nodeId == node.id);

    choiceNode.choice = parseInt(choice, 10);
  }

  /**
   * Removes an entry from the actions array when a node is deactivated.
   * 
   * @param {number} nodeId The deactivated node id.
   */
  removePointNode(nodeId) {
    if (MIDNIGHTDEBUG) console.log(`removing ${nodeId} from history: action length = ${this.actions.length}`);
    for (i = this.actions.length - 1; i >= 0; i--) {
      if (this.actions[i].nodeId == nodeId) {
        this.actions.splice(i, 1);
        break;
      }
    }
    if (MIDNIGHTDEBUG) console.log(`action length = ${this.actions.length} after removing ${nodeId}`);
  }

  show() {
    let _self = this;
    this.actions.forEach(action => {
      let node = _self.tree.nodes[action.nodeId];
      console.log(node.toString());
      if (action.choice == 0 || action.choice == 1) {
        console.log(`\tchoice = ${action.choice}`);
      }
    });
  }
}

class MidnightTalentCalculatorTree {
  isInitialized;
  classData;
  specData;
  talentCalculator;

  /**
   * Create a talent calculator tree object.
   * 
   * @param {MidnightTalentCalculator} talentCalculator The talent calculator object.
   */
  constructor(talentCalculator) {
    this.talentCalculator = talentCalculator;
  }

  /**
   * Initializes the tree based on the given parameters.
   * 
   * @param {string} classId Id of the class whose tree we want to initialize.
   * @param {string} specId Id of the spec whose tree we want to initialize.
   */
  async init(classId, specId) {
    let json = await MidnightTalentCalculatorJSON.get("classes_basic_info");
    this.classData = json.find((cd) => cd.id == classId);
    this.specData = this.classData.specializations.find((sd) => sd.id == specId);
    this.classTree = new MidnightTalentCalculatorClassTree(this, this.classData, this.specData);
    await this.classTree.init_nodes();
    this.specTree = new MidnightTalentCalculatorSpecTree(this, this.classData, this.specData);
    await this.specTree.init_nodes();
    let pvpJSON = await MidnightTalentCalculatorJSON.get("pvp-talents");
    this.pvpTalents = {}
    this.chosenPvPTalents = [null, null, null];
    let self_ = this;
    pvpJSON.forEach(talentJSON => {
      if (talentJSON.specIds.includes(self_.specData.id)) {
        self_.pvpTalents[talentJSON.id] = new MidnightTalentCalculatorPvPTalent(talentJSON);
      }
    });
    this.pvpTalentIds = Object.keys(this.pvpTalents).map(id => parseInt(id)).sort((a,b) => parseInt(a) > parseInt(b) ? 1 : -1);
    let set = new Set();
    json = await MidnightTalentCalculatorJSON.get(this.classData.name);
    json.unusedNodeIds.forEach(nodeId => set.add(nodeId));
    Object.values(json.specs).forEach(specJSON => {
      Object.keys(specJSON.classNodes).forEach(nodeId => set.add(parseInt(nodeId)));
      Object.keys(specJSON.specNodes).forEach(nodeId => set.add(parseInt(nodeId)));
      Object.keys(specJSON.hero.left.nodes).forEach(nodeId => set.add(parseInt(nodeId)));
      Object.keys(specJSON.hero.right.nodes).forEach(nodeId => set.add(parseInt(nodeId)));
      set.add(specJSON.hero.metaNodeId);
      set.add(specJSON.apexNode.id);
      if (specJSON.id == this.specData.id) {
        this.classNodeIds = Object.keys(specJSON.classNodes).map(id => parseInt(id)).sort((a,b) => parseInt(a) > parseInt(b) ? 1 : -1);
        this.specNodeIds = Object.keys(specJSON.specNodes).map(id => parseInt(id)).sort((a,b) => parseInt(a) > parseInt(b) ? 1 : -1);
        this.heroMetaNodeId = specJSON.hero.metaNodeId;
        this.heroRootNodeIds = [specJSON.hero.left.rootNodeId, specJSON.hero.right.rootNodeId];
        this.apexTalentId = specJSON.apexNode.id;
      }
    });
    this.allNodeIds = Array.from(set).sort((a,b) => parseInt(a) > parseInt(b) ? 1 : -1);
    this.deleteHeroTree(); // When switching from one spec to another
  }

  /**
   * Initializes hero tree based on the index of selected hero talent.
   * 
   * @param {Number} index Index of the selected hero talent.
   */
  async initHeroTree(index) {
    this.heroTree = new MidnightTalentCalculatorHeroTree(this, this.classData, this.specData, index);
    this.heroTreeChosen = index;
    await this.heroTree.init_nodes();
    this.heroNodeIds = Object.keys(this.heroTree.nodes).map(id => parseInt(id)).sort((a,b) => parseInt(a) > parseInt(b) ? 1 : -1);
  }

  /**
   * Deletes the hero tree.
   */
  deleteHeroTree() {
    this.heroTree = null;
    this.heroTreeChosen = null;
  }

  /**
   * Sets the chosen pvp talent for a slot with given index.
   * 
   * @param {string} talentId Id of chosen pvp talent.
   * @param {Number} index Index of slot in which the pvp talent will be displayed.
   */
  choosePvPTalent(talentId, index) {
    this.chosenPvPTalents[index] = this.pvpTalents[talentId];
  }

  /**
   * Returns node object with corresponding id to given node id.
   * 
   * @param {string} nodeId Id of the node whose object we want.
   * @returns {ChoiceNode|SquareNode|RoundNode|null} The node object when the node was found. Null if the node wasn't found.
   */
  getNodeFromId(nodeId) {
    let node = this.classTree.nodes[nodeId];
    if (node) {
      return node;
    }
    node = this.specTree.nodes[nodeId];
    if (node) {
      return node;
    }
    if (this.heroTree) {
      node = this.heroTree.nodes[nodeId];
      if (node) {
        return node;
      }
    }

    return null;
  }

}

class MidnightTalentCalculatorSubTree {
  tree;
  classData;
  specData;
  nodes;
  connections;
  spentPoints;
  firstCheckpointSpentPoints;
  secondCheckpointSpentPoints;
  firstCheckpointActive;
  secondCheckpointActive;
  requiredLevel;
  checkpoints;

  /**
   * Create a talent calculator subtree object.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing information about the trees.
   * @param {Object} classData Object containing selected class data.
   * @param {Object} specData Object containing selected spec data.
   */
  constructor(tree, classData, specData) {
    this.tree = tree;
    this.classData = classData;
    this.specData = specData;
    this.spentPoints = 0;
    this.firstCheckpointSpentPoints = 0;
    this.secondCheckpointSpentPoints = 0;
    this.firstCheckpointActive = true;
    this.secondCheckpointActive = true;
    this.requiredLevel = this.defaultRequiredLevel();
    this.hiddenRequiredLevel = this.defaultRequiredLevel();
    this.hiddenRequiredLevelTmp = this.defaultRequiredLevel();
    this.history = new MidnightTalentCalculatorHistory(this);
  }
  /**
   * Asynchronous method. Initializes nodes for current subtree.
   */
  async init_nodes() {
    let classJSON = await MidnightTalentCalculatorJSON.get(this.classData.name);
    let specJSON = Object.values(classJSON.specs).find((spec) => spec.id == this.specData.id)
    let nodesJSON = this.chooseNodes(specJSON);
    this.nodes = {};
    this.connections = {};
    this.checkpoints = this.chooseCheckpoints(specJSON);
    Object.entries(nodesJSON).forEach(([key, value]) => {
      this.nodes[key] = this.init_node(value);
    });
    // Second pass mandatory because JS messes with the order of the entries in
    // the hash and nodes are processed in a seemingly random manner.
    Object.values(this.nodes).forEach((node) => node.init_node());
  }

  /**
   * Initializes a node based on it's type.
   * 
   * @param {Object} node Object containing data about the node that is to be initialized.
   * @returns {ChoiceNode|SquareNode|RoundNode} The initialized node object.
   */
  init_node(node) {
    if (node.type == "round") {
      return new MidnightTalentCalculatorNodeRound(node, this);
    } else if (node.type == "square") {
      return new MidnightTalentCalculatorNodeSquare(node, this);
    } else if (node.type == "choice") {
      return new MidnightTalentCalculatorNodeChoice(node, this);
    } else {
      console.log(`Midnight Talent Calculator: Unhandled node type "${node.type}"`);
    }
  }

  /**
   * Adds a connection to the connections hash.
   * 
   * @param {ChoiceNode|RoundNode|SquareNode} srcNode The source node of the connection.
   * @param {ChoiceNode|RoundNode|SquareNode} dstNode The destination node of the connection.
   */
  addConnection(srcNode, dstNode) {
    this.connections[[srcNode.id, dstNode.id]] = new MidnightTalentCalculatorConnection(srcNode, dstNode);
  }

  /**
   * After removing a talent deactivates talents whose spentAmountRequired condition is no longer met.
   */
  sanitize() {
    Object.values(this.nodes).forEach(node => {
      if (node.state != 'inactive' && node.spentAmountRequired != 0) {
        // Determine which checkpoint the node is linked to
        let toDeactivate = false;
        if (node.spentAmountRequired == this.checkpoints[0].points) {
          toDeactivate = this.firstCheckpointSpentPoints < node.spentAmountRequired;
        } else {
          toDeactivate = this.secondCheckpointSpentPoints < node.spentAmountRequired;
        }
        if (toDeactivate) {
          node.deactivate();
        }
      }
    })
  }

  /**
   * Updates checkpoint demarcators of a tree depending on treeType.
   */
  updateCheckpoints() {
    if (MIDNIGHTDEBUG) {
      console.log(`updateCheckpoint: entering (${this.treeType})`);
    }
    // Function called after adding a point or removing a point
    let toFirstCheckpoint = this.checkpoints[0].points - this.firstCheckpointSpentPoints;
    let toSecondCheckpoint = this.checkpoints[1].points - this.secondCheckpointSpentPoints;
    if (MIDNIGHTDEBUG) {
      console.log(`updateCheckpoint: toFirstCheckpoint = ${toFirstCheckpoint} / toSecondCheckpoint = ${toSecondCheckpoint}`);
    }
    if (toFirstCheckpoint > 0) {
      this.tree.talentCalculator.renderer.updateFirstCheckpointDistance(this.treeType, toFirstCheckpoint);
      if (!this.firstCheckpointActive) {
        this.deactivateNodesAtCheckpoint(0); // 0 for first checkpoint
        this.firstCheckpointActive = true;
      }
    } else if (toFirstCheckpoint == 0 ) {
      if (this.firstCheckpointActive) {
        this.tree.talentCalculator.renderer.hideFirstCheckpointDemarcator(this.treeType);
        this.activateNodesAtCheckpoint(0); // 0 for first checkpoint
        this.firstCheckpointActive = false;
      }
    }
    if (toSecondCheckpoint > 0) {
      this.tree.talentCalculator.renderer.updateSecondCheckpointDistance(this.treeType, toSecondCheckpoint);
      if (!this.secondCheckpointActive) {
        this.deactivateNodesAtCheckpoint(1); // 1 for second checkpoint
        this.secondCheckpointActive = true;
      }
    } else if (toSecondCheckpoint == 0) {
      if (this.secondCheckpointActive) {
        this.tree.talentCalculator.renderer.hideSecondCheckpointDemarcator(this.treeType);
        this.activateNodesAtCheckpoint(1); // 1 for second checkpoint
        this.secondCheckpointActive = false;
      }
    }
    if (MIDNIGHTDEBUG) {
      console.log(`updateCheckpoint: exiting`);
    }
  }

  /**
   * When enough points are spent to satisfy the checkpoint activate the nodes behind the checkpoint.
   * 
   * @param {Number} checkpointIndex Index of the checkpoint.
   */
  activateNodesAtCheckpoint(checkpointIndex) {
    if (MIDNIGHTDEBUG) {
      console.log(`activateNodesAtCheckpoint: entering`);
    }
    Object.values(this.nodes).forEach(node => {
      if (node.spentAmountRequired == this.checkpoints[checkpointIndex].points) {
        if (node.state == 'maxedOut' || node.state == 'permanentlyMaxedOut') return;
        if (node.previousNodes.length == 0) {
          node.state = 'active';
          this.tree.talentCalculator.renderer.refreshNode(node);
        } else {
          node.previousNodes.forEach(node_ => {
            if (node_.state == 'maxedOut' || node_.state == 'permanentlyMaxedOut') {
              node.state = 'active';
              this.tree.talentCalculator.renderer.refreshNode(node);
            }
          });
        }
      }
    });
    if (MIDNIGHTDEBUG) {
      console.log(`activateNodesAtCheckpoint: exiting`);
    }
  }

  /**
   * When ckeckpoint is no longer satisfied disable all nodes below it and remove their points.
   * 
   * @param {Number} checkpointIndex Index of the checkpoint.
   */
  deactivateNodesAtCheckpoint(checkpointIndex) {
    if (MIDNIGHTDEBUG) {
      console.log(`deactivateNodesAtCheckpoint: entering`);
    }
    Object.values(this.nodes).forEach(node => {
      if (node.spentAmountRequired == this.checkpoints[checkpointIndex].points) {
        if (MIDNIGHTDEBUG) console.log(`Deactivating ${node.spellName()}`);
        node.cancel();
      }
    });
    if (MIDNIGHTDEBUG) {
      console.log(`deactivateNodesAtCheckpoint: exiting`);
    }
  }

  /**
   * This method is called when we've invested the last available point in the tree.
   * 
   * Active nodes with 1 or more points invested get locked and displayed as if they were locked out.
   * Active nodes with 0 points invested become inactive.
   */
  lockActiveNodes() {
    Object.values(this.nodes).forEach(node => {
      if (node.state == 'active') {
        if (node.currentPoints > 0) {
          this.tree.talentCalculator.renderer.makeNodeMaxedOut(node);
          const skill = document.querySelector(`#midnight-skill-builder${this.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${node.id}"] div.skill`);
          const skillPointContainer = document.querySelector(`#midnight-skill-builder${this.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${node.id}"] div.skill-point-container`);
          if (node.currentPoints < node.maxPoints) {
            skill.style.border = '2px solid var(--font-color-green)';
            skillPointContainer.style.color = 'var(--font-color-green)';
          }
        } else {
          this.tree.talentCalculator.renderer.makeNodeInactive(node);
          const skill = document.querySelector(`#midnight-skill-builder${this.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${node.id}"] div.skill`);
          const skillPointContainer = document.querySelector(`#midnight-skill-builder${this.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${node.id}"] div.skill-point-container`);
          skill.style.border = '';
          skillPointContainer.style.color = '';
        }
      } else {
          const skill = document.querySelector(`#midnight-skill-builder${this.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${node.id}"] div.skill`);
          const skillPointContainer = document.querySelector(`#midnight-skill-builder${this.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${node.id}"] div.skill-point-container`);
          skill.style.border = '';
          skillPointContainer.style.color = '';
      }
    });
  }

  /**
   * This method is called when we've removed a point from a fully invested tree.
   * 
   * We need to reactivate all locked nodes from reaching the maximum number of invested points.
   * We're basically undoing the `lockActiveNodes` method. 
   */
  unlockActiveNodes() {
    Object.values(this.nodes).forEach(node => {
      if (node.state == 'active') {
        console.log(`unlockActiveNodes: ${node.id}`);
        if (node.currentPoints > 0) {
          this.tree.talentCalculator.renderer.unmakeNodeMaxedOut(node);
        } else {
          this.tree.talentCalculator.renderer.unmakeNodeInactive(node);
        }
      }
    });
  }

  /**
   * Resets an entire subtree.
   */
  reset() {
    this.spentPoints = 0;
    this.firstCheckpointSpentPoints = 0;
    this.secondCheckpointSpentPoints = 0;
    this.firstCheckpointActive = true;
    this.secondCheckpointActive = true;
    this.requiredLevel = this.defaultRequiredLevel();
    this.hiddenRequiredLevel = this.defaultRequiredLevel();

    this.updateCheckpoints();
    this.history.reset();
    Object.values(this.nodes).forEach(node => {
      if (node.state != 'permanentlyMaxedOut') {
        node.reset();
        if (node.previousNodeIds.length == 0 && node.spentAmountRequired == 0) {
          node.state = 'active';
        }
        node.previousNodes.forEach(previousNode => {
          if (previousNode.state == 'permanentlyMaxedOut') {
            node.state = 'active';
          }
        });
        this.tree.talentCalculator.renderer.refreshNode(node);
        node.nextNodes.forEach(nextNode => {
          this.connections[[node.id, nextNode.id]].makeInactive();
        });
      }
    });
    this.tree.talentCalculator.renderer.refreshSpentPointsContainer(this, this.treeType);
    this.tree.talentCalculator.renderer.refreshRequiredLevelContainer(this, this.treeType);    
  }

  showHistory() {
    if (MIDNIGHTDEBUG) console.log("History");
    this.history.actions.forEach(action => {
      let nodeId = action.nodeId;
      let node = this.nodes[nodeId];
      if (action.choice == 0 || action.choice == 1) {
        if (MIDNIGHTDEBUG) console.log(`\t${node.spellName()} - choice ${action.choice}`);
      } else {
        if (MIDNIGHTDEBUG) console.log(`\t${node.spellName()}`);
      }
    });
  }
}

/**
 * Represents the selected class talent tree.
 */
class MidnightTalentCalculatorClassTree extends MidnightTalentCalculatorSubTree {
  maxPoints = 34;
  requiredLevelMidnight = {
    baseMaxPoints: 31,
    levels: [82, 85, 88]
  }
  treeType = 'class';

  /**
   * Returns an object with all nodes of the selected class tree.
   * 
   * @param {Object} json Object containing data about the trees.
   * @returns {Object} Object containing objects that represent each of the nodes in the selected class tree.
   */
  chooseNodes(json) {
    return json.classNodes;
  }

  /**
   * Returns an array of all connections between nodes of the selected class tree.
   * 
   * @param {Object} json Object containing data about the trees. 
   * @returns {Array} Array of connections between nodes of the selected class tree.
   */
  chooseCheckpoints(json) {
    return json.classCheckpoints;
  }

  /**
   * Defines the default level required to activate a node in the selected class tree.
   * 
   * @returns {Number} The required level value.
   */
  defaultRequiredLevel() {
    return 10;
  }
}

/**
 * Represents the selected spec tree.
 */
class MidnightTalentCalculatorSpecTree extends MidnightTalentCalculatorSubTree {
  maxPoints = 34;
  requiredLevelMidnight = {
    baseMaxPoints: 30,
    levels: [81, 84, 87, 90]
  }
  treeType = 'spec';

  async init_nodes() {
    await super.init_nodes();
    const classJSON = await MidnightTalentCalculatorJSON.get(this.classData.name);
    const specJSON = Object.values(classJSON.specs).find((spec) => spec.id == this.specData.id);
    const apexJSON = specJSON.apexNode;
    this.apexTalent = new MidnightTalentCalculatorApexTalent(apexJSON, this);
  }

  /**
   * Returns an object with all nodes of the selected spec tree.
   * 
   * @param {Object} json Object containing data about the trees. 
   * @returns {Object} Object containing objects that represent each of the nodes in the selected spec tree.
   */
  chooseNodes(json) {
    return json.specNodes;
  }

  /**
   * Returns an array of all connections between noes of the selected spec tree.
   * 
   * @param {Object} json Object containing data about the trees.
   * @returns {Array} Array of connections between nodes of the selected spec tree.
   */
  chooseCheckpoints(json) {
    return json.specCheckpoints;
  }

  /**
   * Defines the default level required to activate a node in the selected spec tree.
   * 
   * @returns {Number} The required level value.
   */
  defaultRequiredLevel() {
    return 11;
  }

  reset() {
    super.reset();
    this.apexTalent.reset();
  }
}

/**
 * Represents the selected hero talent tree.
 * @extends MidnightTalentCalculatorSubTree
 */
class MidnightTalentCalculatorHeroTree extends MidnightTalentCalculatorSubTree {
  maxPoints = 13;
  requiredLevelMidnight = {
    baseMaxPoints: 10,
    levels: [83, 86, 89]
  }
  icon;
  treeType = 'hero';

  /**
   * Creates a hero talent tree object.
   * 
   * Firstly calls the constructor of parent class `MidnightTalentCalculatorSubTree` using `super()`.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing information about the trees.
   * @param {Object} classData Object containing data of the selected class tree.
   * @param {Object} specData Object containing data of the selected spec tree.
   * @param {Number} index Index of the selected hero talents.
   */
  constructor(tree, classData, specData, index) {
    super(tree, classData, specData);
    this.index = index;
  }

  /**
   * Asynchronous method. Initializes nodes for the hero tree. 
   */
  async init_nodes() {
    let classJSON = await MidnightTalentCalculatorJSON.get(this.classData.name);
    let specJSON = Object.values(classJSON.specs).find((spec) => spec.id == this.specData.id)
    let heroJSON = null;
    if (this.index == 0) { 
      heroJSON = specJSON.hero.left;
      this.name = heroJSON.name;
      this.discardedHeroTreeRootNodeId = specJSON.hero.right.rootNodeId;
    } else {
      heroJSON = specJSON.hero.right;
      this.name = heroJSON.name;
      this.discardedHeroTreeRootNodeId = specJSON.hero.left.rootNodeId;
    }
    this.nodes = {};
    this.connections = {};
    this.icon = heroJSON.icon;
    Object.entries(heroJSON.nodes).forEach(([key, value]) => {
      this.nodes[key] = this.init_node(value);
    });
    // Second pass mandatory because JS messes with the order of the entries in
    // the hash and nodes are processed in a seemingly random manner.
    Object.values(this.nodes).forEach((node) => node.init_node());
  }

  /**
   * Defines the default level required to activate a node in the selected hero talent tree.
   * 
   * @returns {Number} The required level value.
   */
  defaultRequiredLevel() {
    return 70;
  }
  /**
   * No checkpoints for hero trees, so sanitize does nothing
   */
  sanitize() { }

  /**
   * No checkpoints for hero trees, so updateCheckpoints does nothing
   */
  updateCheckpoints() { }

  /**
   * No checkpoints for hero trees, so activateNodesAtCheckpoint does nothing
   */
  activateNodesAtCheckpoint(checkpointIndex) { }

  /**
   * No checkpoints for hero trees, so deactivateNodesAtCheckpoint does nothing
   */
  deactivateNodesAtCheckpoint(checkpointIndex) { }
}

class MidnightTalentCalculatorApexTalent {
  usedPoints = 0;
  maxPoints = 4;
  currReqLvl = 0;

  constructor(apexJSON, specTree) {
    this.name = apexJSON.name;
    this.icon = apexJSON.icon;
    this.type = apexJSON.type;
    this.id = apexJSON.id;
    this.specTree = specTree;
    this.levels = specTree.requiredLevelMidnight.levels;
    this.spells = apexJSON.spells;
    this.spells.forEach(spell => spell.usedPoints = 0);
  }

  leftClick(talentIndex, loadFromHash = false) {
    if (!loadFromHash && (talentIndex === -1 || this.specTree.spentPoints === this.specTree.maxPoints || this.specTree.spentPoints < 20)) return;

    this.spells[talentIndex].usedPoints++;
    this.usedPoints++;
    this.specTree.spentPoints++;
    if (this.specTree.spentPoints === this.specTree.maxPoints) this.specTree.lockActiveNodes();
    this.currReqLvl = this.levels[this.usedPoints - 1];
    this.specTree.requiredLevel = Math.max(this.specTree.hiddenRequiredLevel, this.currReqLvl);
    this.specTree.tree.talentCalculator.renderer.refreshSpentPointsContainer(this.specTree, this.specTree.treeType);
    this.specTree.tree.talentCalculator.renderer.refreshRequiredLevelContainer(this.specTree, this.specTree.treeType);
    this.specTree.history.addPointRoundSquareNode(this.id);
    this.specTree.tree.talentCalculator.renderer.updateHash();
  }

  rightClick(talentIndex) {
    if (talentIndex === -1 || specTree.spentPoints === 0) return;

    this.spells[talentIndex].usedPoints--;
    this.usedPoints--;
    if (this.specTree.spentPoints === this.specTree.maxPoints) this.specTree.unlockActiveNodes();
    this.specTree.spentPoints--;
    if (this.usedPoints === 0) {
      this.currReqLvl = 0;
    } else {
      this.currReqLvl = this.levels[this.usedPoints - 1];
    }
    this.specTree.requiredLevel = Math.max(this.specTree.hiddenRequiredLevel, this.currReqLvl);
    this.specTree.tree.talentCalculator.renderer.refreshSpentPointsContainer(this.specTree, this.specTree.treeType);
    this.specTree.tree.talentCalculator.renderer.refreshRequiredLevelContainer(this.specTree, this.specTree.treeType);
    this.specTree.history.removePointNode(this.id);
    this.specTree.tree.talentCalculator.renderer.updateHash();
  }

  reset() {
    this.usedPoints = 0;
    this.currReqLvl = 0;
    this.spells.forEach((spell, index) => {
      spell.usedPoints = 0;
      this.specTree.history.removePointNode(this.id);
      // TODO: use when apex talent spell icon is available
      // this.specTree.tree.talentCalculator.renderer.updateApexTalentSpell(this, index);
      this.specTree.tree.talentCalculator.renderer.updateApexTalentPointCounter(this);
    });
    this.specTree.requiredLevel = this.specTree.hiddenRequiredLevel;
    this.specTree.tree.talentCalculator.renderer.updateApexTalentIcon(this.specTree);
  }
}

class MidnightTalentCalculatorConnection {
  /**
   * Create a connection object.
   * 
   * @param {ChoiceNode|SquareNode|RoundNode} srcNode Object representing the source node of the connection.
   * @param {ChoiceNode|SquareNode|RoundNode} dstNode Object representing the destination node of the connection.
   */
  constructor (srcNode, dstNode) {
    this.srcNode = srcNode;
    this.dstNode = dstNode; 
    this.state = 'inactive';
  }

  /**
   * Sets the state of a connection to `active` and renders the connection as active.
   */
  makeActive() {
    this.state = 'active';
    this.srcNode.subtree.tree.talentCalculator.renderer.makeConnectionActive(this);
  }

  /**
   * Sets the state of a connection to `inactive` and renders the connection as inactive.
   */
  makeInactive() {
    this.state = 'inactive';
    this.srcNode.subtree.tree.talentCalculator.renderer.makeConnectionInactive(this);
  }
}

class MidnightTalentCalculatorNode {
  subtree;
  id;
  row;
  column;
  spells;
  spentAmountRequired;
  nextNodes = [];
  previousNodes = [];
  /**
   * The state the node is currently in.
   * 
   * Can be one of the following:
   * `permanentlyMaxedOut` - if talent is already maxed out by default, left/right clicking does nothing
   * `inactive` - left/right clicking does nothing
   * `active` - if left clicked invests a point to this talent, right clicking removes a point from this talent (if there are none points invested right clicking does nothing)
   * `maxedOut` - left clicking does nothing, right clicking removes a point and deactivates all next nodes
   * 
   * @type {string}
   */
  state;
  currentPoints;
  maxPoints;

  /**
   * Creates a node object.
   * 
   * @param {Object} json Object containing data about the trees.
   * @param {ClassTree|SpecTree|HeroTree} subtree The tree in which the node lies.
   */
  constructor(json, subtree) {
    this.subtree = subtree;
    this.id = json.id;
    this.column = json.column;
    this.row = json.row;
    this.spells = json.spells;
    this.spentAmountRequired = json.spentAmountRequired;
    this.previousNodeIds = json.previousNodeIds;
    this.alreadyMaxedOut = json.alreadyMaxedOut;

    // We init the state here, because we need it in init_node when we check the state of other nodes
    this.currentPoints = 0;
    this.maxPoints = this.spells[0]?.maxRanks ?? 1;
    if (this.alreadyMaxedOut) {
      this.currentPoints = 1;
      this.maxPoints = this.spells[0]?.maxRanks ?? 1;
      this.state = 'permanentlyMaxedOut';
    } else if (this.previousNodeIds.length == 0 && this.spentAmountRequired == 0 && this.state != 'permanentlyMaxedOut') {
      this.state = 'active';
    } else {
      this.state = 'inactive';
    }
  }

  /**
   * Initializes the node.
   * 
   * Creates the array of next nodes of the node being initialized.
   * Creates the array of the previous nodes of the node being initialized.
   * Adds connections between initialized node and previous nodes.
   * Activates the connections and activates nodes that are to be active.
   */
  init_node() {
    if (MIDNIGHTDEBUG) {
      console.log(`init_node: entering ${this.toString()}`);
    }
    this.previousNodeIds.forEach((nodeId) => {
      let node = this.subtree.nodes[nodeId];
      node.addNextNode(this);
      this.addPreviousNode(node);
      this.subtree.addConnection(node, this);
    });    

    this.previousNodes.forEach(node => {
      if (node.state == 'permanentlyMaxedOut') {
        this.subtree.connections[[node.id, this.id]].makeActive(); 
        if (this.state != 'permanentlyMaxedOut' && this.spentAmountRequired == 0) this.state = 'active';
      }
    });
    if (MIDNIGHTDEBUG) {
      console.log(`init_node: exiting ${this.toString()}`);
    }
  }

  /**
   * Adds a node to the nextNodes array of current node.
   * 
   * @param {ChoiceNode|SquareNode|RoundNode} node The node that is to be added to the nextNodes array.
   */
  addNextNode(node) {
    this.nextNodes.push(node);
  }

  /**
   * Adds a node to the previousNodes array of current node.
   * 
   * @param {ChoiceNode|SquareNode|RoundNode} node The node that is to be added to the previousNodes array.
   */
  addPreviousNode(node) {
    this.previousNodes.push(node);
  }

  /**
   * Makes a node active and renders the node as active.
   * 
   * This method is only called from the `leftClick` method of a previous node having become maxedOut.
   * If the node is not inactive do nothing.
   * If the node is inactive we check if the spentAmountRequired condition is fulfilled and we make the node active.
   */
  makeActive() {
    if (this.state == 'inactive') {
      if (this.subtree.spentPoints >= this.spentAmountRequired) {
        this.state = 'active';
        this.subtree.tree.talentCalculator.renderer.refreshNode(this);
      }
    }
  }

  /**
   * Method only called when right clicking this node.
   * 
   * This method only does something if the node's state is `maxedOut` or if the node's state
   * is `active` with 1 or more points invested.
   */
  rightClick() {
    if (this.state == 'active' && this.currentPoints >= 1) {
      // Fairly straightforward, we remove a point and ask the renderer to refresh the node
      this.removePoint();
      this.subtree.history.removePointNode(this.id);
      this.subtree.updateCheckpoints();
      this.subtree.tree.talentCalculator.renderer.refreshNode(this);
      this.subtree.tree.talentCalculator.renderer.updateHash();
    } else if (this.state == 'maxedOut') {
      this.removePoint();
      this.subtree.history.removePointNode(this.id);
      this.state = 'active';
      this.subtree.tree.talentCalculator.renderer.refreshNode(this);
      // We go through every next node and update it, this going to recursively process
      // all the follow-up nodes that had points invested in them
      this.cancelNextNodes();
      // After that, we need to do a sanity check on the tree to make sure that
      // we deselect nodes whose spentAmountRequired condition is no longer met.
      this.subtree.sanitize();
      this.subtree.updateCheckpoints();
      if (this.subtree instanceof MidnightTalentCalculatorSpecTree && this.subtree.spentPoints - this.subtree.apexTalent.usedPoints < 20) {
        this.subtree.apexTalent.reset();
      }
      this.subtree.tree.talentCalculator.renderer.updateHash();
      this.subtree.tree.talentCalculator.renderer.refreshSpentPointsContainer(this.subtree, this.subtree.treeType);
      this.subtree.tree.talentCalculator.renderer.refreshRequiredLevelContainer(this.subtree, this.subtree.treeType);
    }
    this.subtree.tree.talentCalculator.renderer.updateApexTalentIcon(this.subtree.tree.specTree);
  }

  /**
   * Cancels the next nodes of the current node.
   * 
   * Calls the `cancel` method on a node from the next nodes array if it's state isn't `inactive`.
   * Makes the connections between the nodes from the next nodes array and the current node inactive. 
   */
  cancelNextNodes() {
    if (MIDNIGHTDEBUG)
      console.log(`cancelNextNodes: entering ${this.toString()}`);
    this.nextNodes.forEach(node => {
      if (node.state != 'inactive') {
        node.cancel();
      }
      this.subtree.connections[[this.id, node.id]].makeInactive();
    });
    if (MIDNIGHTDEBUG) {
      console.log(`cancelNextNodes: exiting ${this.toString()}`);
    }
  }

  /**
   * Cancels a node.
   * 
   * If a node is right clicked and is maxed out or active it removes points from that talent until there are no
   * current points spent on that talent. Removes this node from the history and sets it's state to `inactive`.
   * 
   * @returns If a previous node whose state is `maxedOut` or `permanentlyMaxedOut` is found.
   */
  cancel() {
    if (MIDNIGHTDEBUG)
      console.log(`cancel: entering ${this.toString()}`);
    // if one of the previous nodes is maxedOut or permanentlyMaxedOut, we do nothing
    let foundMaxedOutNode = false
    this.previousNodes.forEach(node => {
      if (node.state == 'maxedOut' || node.state == 'permanentlyMaxedOut') {
        if (MIDNIGHTDEBUG) console.log(`Found maxed out node ${node.spellName()} for ${this.spellName()}`);
        foundMaxedOutNode = true;
      }
    });
    if (foundMaxedOutNode) {
      if (MIDNIGHTDEBUG) console.log(`cancel: exiting after finding a maxedOut or permanentlyMaxedOut previous node for ${this.spellName()}`);
      return;
    }
    let originalState = this.state;
    if (this.state == 'maxedOut' || this.state == 'active') {
      while (this.currentPoints != 0) {
        if (MIDNIGHTDEBUG) console.log(`cancel: removing point for ${this.spellName()}`);
        this.removePoint();
        this.subtree.history.removePointNode(this.id);
      }
      this.state = 'inactive';
      document.querySelector(`#midnight-skill-builder${this.subtree.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${this.id}"] div.skill`).style.border = '';
      document.querySelector(`#midnight-skill-builder${this.subtree.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${this.id}"] div.skill-point-container`).style.color = '';
    }
    this.subtree.tree.talentCalculator.renderer.refreshNode(this)
    if (originalState != 'inactive') {
      // No need to process the next Nodes if the node was already inactive
      this.cancelNextNodes();
    }
    if (MIDNIGHTDEBUG) {
      console.log(`cancel: exiting ${this.toString()}`);
    }
  }

  /**
   * Deactivates a node and all of the connections from this node to it's next nodes.
   */
  deactivate() {
    while (this.currentPoints != 0) {
      this.removePoint();
      this.subtree.history.removePointNode(this.id);
    }
    this.state = 'inactive';
    this.subtree.tree.talentCalculator.renderer.refreshNode(this)
    this.nextNodes.forEach(node => {
      this.subtree.connections[[this.id, node.id]].makeInactive();
    });
  }

  /**
   * Adds a point to the current points counter.
   * 
   * Increments the `currentPoints` property by 1.
   * Increments the `spentPoints` property of the subtree the node is in by 1.
   * Updates the required level value of the subtree the node is in.
   */
  addPoint() {
    if (MIDNIGHTDEBUG) {
      console.log(`addPoint: entering ${this.toString()}`);
    }
    this.currentPoints += 1;
    if (this.currentPoints > this.maxPoints) {
      console.error(`node ${this.id} (${this.name}) has reached ${this.currentPoints} point(s), but max is ${this.maxPoints}`);
    }
    this.subtree.spentPoints += 1;
    this.subtree.tree.talentCalculator.renderer.refreshSpentPointsContainer(this.subtree, this.subtree.treeType);
    if (this.subtree.spentPoints !== 1 && (this.treeType === 'class' || this.treeType === 'spec')) {
      if (this.subtree.spentPoints - (this.subtree.apexTalent?.usedPoints ?? 0) > this.subtree.requiredLevelMidnight.baseMaxPoints) {
        this.subtree.hiddenRequiredLevel = this.subtree.requiredLevelMidnight.levels[this.subtree.spentPoints - (this.subtree.apexTalent?.usedPoints ?? 0) - this.subtree.requiredLevelMidnight.baseMaxPoints - 1];
      } else {
        this.subtree.hiddenRequiredLevel += 2;
        if (this.subtree.spentPoints - (this.subtree.apexTalent?.usedPoints ?? 0) === this.subtree.requiredLevelMidnight.baseMaxPoints) this.subtree.hiddenRequiredLevelTmp = this.subtree.hiddenRequiredLevel;
      }
      this.subtree.requiredLevel = Math.max(this.subtree.hiddenRequiredLevel, this.subtree.apexTalent?.currReqLvl ?? 0);
    }
    else if (this.treeType == 'hero') {
      if (this.subtree.spentPoints > this.subtree.requiredLevelMidnight.baseMaxPoints) {
        this.subtree.hiddenRequiredLevel = this.subtree.requiredLevelMidnight.levels[this.subtree.spentPoints - this.subtree.requiredLevelMidnight.baseMaxPoints - 1];
      } else {
        this.subtree.hiddenRequiredLevel += 1;
        if (this.subtree.spentPoints === this.subtree.requiredLevelMidnight.baseMaxPoints) this.subtree.hiddenRequiredLevelTmp = this.subtree.hiddenRequiredLevel;
      }
      this.subtree.requiredLevel = this.subtree.hiddenRequiredLevel;
    }
    this.subtree.tree.talentCalculator.renderer.refreshRequiredLevelContainer(this.subtree, this.treeType);      
    if (this.subtree.spentPoints > this.subtree.maxPoints) {
      console.error(`Adding a point to node ${this.id} (${this.name}) caused the subtree to reach ${this.subtree.spentPoints} points, when max is ${this.subtree.maxPoints}`);
    }
    if (this.spentAmountRequired == 0) {
      this.subtree.firstCheckpointSpentPoints += 1;
      this.subtree.secondCheckpointSpentPoints += 1;
    } else if (this.spentAmountRequired == this.subtree.checkpoints[0].points) {
      this.subtree.secondCheckpointSpentPoints += 1;
    }
    if (MIDNIGHTDEBUG) {
      console.log(`addPoint: exiting ${this.toString()}`);
    }
  }

  /**
   * Removes a point from the current points counter.
   * 
   * Decrements the `currentpoints` property by 1.
   * Decrements the `spentPoints` property of the subtree the node is in by 1.
   * Updates the required level value of the subtree the node is in.
   */
  removePoint() {
    this.currentPoints -= 1;
    if (this.getNodeType() == 'choice') {
      this.subtree.tree.talentCalculator.renderer.deselectChoiceNodeSkill(this);
    }
    if (this.currentPoints < 0) {
      console.log(`node ${this.id} (${this.name}) has reached ${this.currentPoints} point(s), but min is 0`);
    }
    this.subtree.spentPoints -= 1;
    this.subtree.tree.talentCalculator.renderer.refreshSpentPointsContainer(this.subtree, this.subtree.treeType);
    if (this.subtree.spentPoints !== 0 && (this.treeType === 'class' || this.treeType === 'spec')) {
      if (this.subtree.spentPoints - (this.subtree.apexTalent?.usedPoints ?? 0) > this.subtree.requiredLevelMidnight.baseMaxPoints) {
        this.subtree.hiddenRequiredLevel = this.subtree.requiredLevelMidnight.levels[this.subtree.spentPoints - (this.subtree.apexTalent?.usedPoints ?? 0) - this.subtree.requiredLevelMidnight.baseMaxPoints - 1];
      } else if (this.subtree.spentPoints - (this.subtree.apexTalent?.usedPoints ?? 0) === this.subtree.requiredLevelMidnight.baseMaxPoints) {
        this.subtree.hiddenRequiredLevel = this.subtree.hiddenRequiredLevelTmp;
      } else {
        this.subtree.hiddenRequiredLevel -= 2;
      }
      this.subtree.requiredLevel = Math.max(this.subtree.hiddenRequiredLevel, this.subtree.apexTalent?.currReqLvl ?? 0);
    }
    else if (this.treeType == 'hero') {
      if (this.subtree.spentPoints > this.subtree.requiredLevelMidnight.baseMaxPoints) {
        this.subtree.hiddenRequiredLevel = this.subtree.requiredLevelMidnight.levels[this.subtree.spentPoints - this.subtree.requiredLevelMidnight.baseMaxPoints - 1];
      } else if (this.subtree.spentPoints === this.subtree.requiredLevelMidnight.baseMaxPoints) {
        this.subtree.hiddenRequiredLevel = this.subtree.hiddenRequiredLevelTmp;
      } else {
        this.subtree.hiddenRequiredLevel -= 1;
      }
      this.subtree.requiredLevel = this.subtree.hiddenRequiredLevel;
    }
    this.subtree.tree.talentCalculator.renderer.refreshRequiredLevelContainer(this.subtree, this.treeType);
    if (this.subtree.spentPoints == this.subtree.maxPoints - 1) {
      // There is once again an available point to spend, so we need to reenable
      // the nodes we had disabled with lockActiveNodes()
      this.subtree.unlockActiveNodes();
    }
    if (this.subtree.spentPoints < 0) {
      console.log(`Removing a point from node ${this.id} (${this.name}) cause the subtree to reach ${this.subtree.spentPoints} points, when min is 0`);
    }
    if (this.spentAmountRequired == 0) {
      this.subtree.firstCheckpointSpentPoints -= 1;
      this.subtree.secondCheckpointSpentPoints -= 1;
    } else if (this.spentAmountRequired == this.subtree.checkpoints[0].points) {
      this.subtree.secondCheckpointSpentPoints -= 1;
    }
  }

  /**
   * Returns the type of incoming node.
   * 
   * @param {ChoiceNode|RoundNode|SquareNode} node The node whose type we want.
   * @returns {string} Type of the node.
   */
  getNodeType() {
    if (this instanceof MidnightTalentCalculatorNodeChoice) return 'choice';
    else if (this instanceof MidnightTalentCalculatorNodeRound) return 'passive';
    else if (this instanceof MidnightTalentCalculatorNodeSquare) return 'active';
    else console.log(`Unknown node type: ${this.constructor.name}`);
  }

  /**
   * Returns a string representation of the node's data.
   * 
   * @returns {string} A string containing the id, name of associated spell, state and values of node's current points invested and max possible points.
   */
  toString() {
    return `${this.id} | ${this.spellName()} | ${this.state} | ${this.currentPoints}/${this.maxPoints} | ${this.spentAmountRequired}`;
  }
}

class MidnightTalentCalculatorNodeRoundSquare extends MidnightTalentCalculatorNode {
  /**
   * Handles the left click event on the node object element.
   * This method is only called when the node is left clicked on.
   * @param {Object} param0 
   * @param {boolean} [param0.force=false] 
   */
  leftClick({ force = false } = {}) {
    if (this.subtree.spentPoints < this.subtree.maxPoints || force) {
      if (MIDNIGHTDEBUG) {
        console.log(`leftClick: entering ${this.toString()}`);
      }
      if (this.state == 'active' || force) { // If active, it means there is room for at least 1 point to be invested
        this.addPoint();
        this.subtree.history.addPointRoundSquareNode(this.id);
        this.subtree.tree.talentCalculator.renderer.updateHash();
        if (this.currentPoints == this.maxPoints) { // Node becomes maxedOut and outgoing connections, if any, unlock
          this.state = 'maxedOut';
          this.nextNodes.forEach((node) => {
            this.subtree.connections[[this.id, node.id]].makeActive();
            this.subtree.tree.talentCalculator.renderer.drawNodeConnection(this.subtree.connections[[this.id, node.id]]);
            node.makeActive();
          });
        }
        this.subtree.updateCheckpoints();
        this.subtree.tree.talentCalculator.renderer.refreshNode(this);
        if (this.subtree.spentPoints == this.subtree.maxPoints) {
          this.subtree.lockActiveNodes();
        }
        this.subtree.tree.talentCalculator.renderer.updateApexTalentIcon(this.subtree.tree.specTree);
      }
      if (MIDNIGHTDEBUG) {
        console.log(`leftClick: exiting ${this.toString()}`);
      }
    }
  }

  /**
   * Resets the node.
   * 
   * A node cannot be reset if its maxed out by default.
   */
  reset() {
    if (this.state != 'permanentlyMaxedOut') {
      this.state = 'inactive';
      this.currentPoints = 0;
      document.querySelector(`#midnight-skill-builder${this.subtree.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${this.id}"] div.skill`).style.border = '';
      document.querySelector(`#midnight-skill-builder${this.subtree.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${this.id}"] div.skill-point-container`).style.color = '';
    }
  }

  /**
   * Returns the name of the associated spell.
   * 
   * @returns {string} Name of the associated spell.
   */
  spellName() {
    return this.spells[0]?.name ?? "";
  }
}

class MidnightTalentCalculatorNodeRound extends MidnightTalentCalculatorNodeRoundSquare { }
class MidnightTalentCalculatorNodeSquare extends MidnightTalentCalculatorNodeRoundSquare { }
class MidnightTalentCalculatorNodeChoice extends MidnightTalentCalculatorNode {
  /**
   * The index of currently chosen skill.
   * Can be either 0/'0' or 1/'1'.
   * @type {string|Number}
   */
  activeChoice;

  /**
   * Handles the left click event on the node object element.
   * This method is only called when the node is left clicked on.
   * 
   * @param {string|Number} choice The index of the chosen skill.
   * @param {Object} param1 
   * @param {boolean} [param1.force=false] 
   */
  leftClick(choice, { force = false } = {}) {
    if (MIDNIGHTDEBUG) {
      console.log(`leftClick: entering ${this.toString()}`);
    }
    if (this.subtree.spentPoints < this.subtree.maxPoints || force) {
      // Can only left click if the choices have popped up, which can only happen if the talent is active
      this.activeChoice = choice;
      this.addPoint();
      this.subtree.history.addPointChoiceNode(this, choice);
      this.subtree.tree.talentCalculator.renderer.updateHash();
      this.state = 'maxedOut';
      this.subtree.tree.talentCalculator.renderer.refreshNode(this, choice);
      this.nextNodes.forEach((node) => {
        this.subtree.connections[[this.id, node.id]].makeActive();
        this.subtree.tree.talentCalculator.renderer.drawNodeConnection(this.subtree.connections[[this.id, node.id]]);
        node.makeActive();
      });
      this.subtree.updateCheckpoints();
      if (this.subtree.spentPoints == this.subtree.maxPoints) {
        this.subtree.lockActiveNodes();
      }
      this.subtree.tree.talentCalculator.renderer.updateApexTalentIcon(this.subtree.tree.specTree);
    }
    if (MIDNIGHTDEBUG) {
      console.log(`leftClick: exiting ${this.toString()}`);
    }
  }

  /**
   * Toggles between the available skills.
   */
  toggleChoice() {
    this.activeChoice = 1 - this.activeChoice;
    this.subtree.history.updatePointChoiceNode(this, this.activeChoice);
  }

  /**
   * Resets the choice node.
   * 
   * Includes deselecting the selected skill.
   */
  reset() {
    this.state = 'inactive';
    this.currentPoints = 0;
    this.activeChoice = null;

    const nodeHTML = document.querySelector(`#midnight-skill-builder${this.subtree.tree.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${this.id}"]`);
    Array.from(nodeHTML.querySelector('div.skill-icon').children).forEach(skill => {
      skill.classList.remove('selected');
    });

    nodeHTML.querySelector(`div.skill`).style.border = '';
    nodeHTML.querySelector(`div.skill-point-container`).style.color = '';
  }

  /**
   * Returns the names of available skills.
   * 
   * @returns {string} The names of available skills.
   */
  spellName() {
    return `${this.spells[0].name}/${this.spells[1].name}`;
  }
}

class MidnightTalentCalculatorPvPTalent {
  /**
   * Creates a pvp talent object.
   * 
   * @param {Object} json Object containing data of a specific PVP talent.
   */
  constructor(json) {
    this.id = json.id;
    this.name = json.name;
    this.icon = json.icon;
    this.description = json.description;
    this.requiredLevel = json.requiredLevel;
  }
}

/**
 * Handles the rendering actions.
 */
class MidnightTalentCalculatorRenderer {
  targetElement;
  talentCalculator;
  imagePath = "/proxy/icy-veins/images/wow/midnight-talent-calculator";
  hashLocked = false;

  /**
   * Creates a renderer object.
   * 
   * @param {HTMLDivElement} targetElement The DOM element inside of which all content will be managed.
   * @param {MidnightTalentCalculator} talentCalculator Object containing information about the talent calculator.
   */
  constructor(targetElement, talentCalculator, idSuffix, embed, displayLevels, heroOnly, collapseEmbed) {
    this.idSuffix = idSuffix;
    this.targetElement = targetElement;
    this.talentCalculator = talentCalculator;
    this.embed = embed;
    this.displayLevels = displayLevels;
    this.heroOnly = heroOnly;
    this.collapseEmbed = collapseEmbed;
  }

  /**
   * Asynchronous method. Renders a selection interface granting the ability to select a playable class.
   */
  async drawClassSelectors() {
    // If classSelector already exists, we do not create it again (note that this
    // case shouldn't happen)
    let classSelector = document.getElementById(`class-selector${this.idSuffix}`);
    if (!classSelector) {
      let classSelectors = `
          <div id="class-selector${this.idSuffix}" class="flex"></div>
          <div id="skill-builder${this.idSuffix}">
            <div class="playable-class-selection flex">
      `
      let json = await MidnightTalentCalculatorJSON.get("classes_basic_info");
      json.forEach((classData) => {
        classSelectors += `
                <div class="playable-class" data-class-id="${classData.id}">
                    <div class="class-icon icon_class_${classData.name}"></div>
                    <p class="class-name">${classData.displayName}</p>
                </div>
        `
      });
      classSelectors += `
            </div>            
          </div>
        <div id="import-popup${this.idSuffix}" class="import-popup flex direction-column">
          <div class="flex header">
            <div class="title">Import Talent Tree</div>
            <button class="close-import-popup">+</button>
          </div>
          <input class="import-string-container" placeholder="Enter import string here"></input>
          <button class="user-action-button import-talent-tree">Import</button>
        </div>
        <div id="user-message${this.idSuffix}" class="user-message flex">
          <span></span>
          <button></button>
        </div>
      `
      this.targetElement.innerHTML = classSelectors;
      this.talentCalculator.eventListenerManager.classSelectors();
      this.talentCalculator.eventListenerManager.importTalentsPopup();
      this.talentCalculator.eventListenerManager.userMessageCloseButton();
    }
  }

  /**
   * Renders the containers for the class, spec and hero trees in embed display.
   */
  drawTreeContainerEmbed() {
    let embedHTML = ``;
    if (!this.heroOnly) {
      embedHTML += `
        <div class="space-between direction-column flex" data-midnight-embedded-build="1" data-type="class"></div>
        <div class="space-between direction-column flex" data-midnight-embedded-build="2" data-type="spec"></div>
      `;
    }
    embedHTML += `
      <div class="space-between direction-column flex" data-midnight-embedded-build="3" data-type="hero"></div>
      <div id="user-message${this.idSuffix}" class="user-message flex">
        <span></span>
        <button></button>  
      </div>
    `;
    if (!this.heroOnly) {
      embedHTML += `
        <div class="control-buttons-container flex">
          <div class="embed-export-open flex">
            <div class="flex button-container export-talents">
              <svg width="16px" height="16px" viewBox="0 0 24 24" fill="#ffffff">
                <path d="M6.9998 6V3C6.9998 2.44772 7.44752 2 7.9998 2H19.9998C20.5521 2 20.9998 2.44772 20.9998 3V17C20.9998 17.5523 20.5521 18 19.9998 18H16.9998V20.9991C16.9998 21.5519 16.5499 22 15.993 22H4.00666C3.45059 22 3 21.5554 3 20.9991L3.0026 7.00087C3.0027 6.44811 3.45264 6 4.00942 6H6.9998ZM8.9998 6H16.9998V16H18.9998V4H8.9998V6Z"></path>
              </svg>
              Export Talents
            </div>
            <a class="flex button-container open-in-calculator" target="_blank" href="${(this.talentCalculator.ptr ? "/wow/midnight-talent-calculator-ptr" : "/wow/midnight-talent-calculator") + this.talentCalculator.hash}">
              <svg width="16px" height="16px" viewBox="0 0 24 24" fill="#ffffff">
                <path d="M4 2H20C20.5523 2 21 2.44772 21 3V21C21 21.5523 20.5523 22 20 22H4C3.44772 22 3 21.5523 3 21V3C3 2.44772 3.44772 2 4 2ZM7 12V14H9V12H7ZM7 16V18H9V16H7ZM11 12V14H13V12H11ZM11 16V18H13V16H11ZM15 12V18H17V12H15ZM7 6V10H17V6H7Z"></path>
              </svg>              
              Open in Calculator
            </a>         
          </div>
        </div>      
      `;
    }

    console.log(this.idSuffix);
    document.querySelector(`#midnight-skill-builder${this.idSuffix}`).insertAdjacentHTML('beforeend', embedHTML);
    this.talentCalculator.eventListenerManager.userMessageCloseButton();
    this.talentCalculator.eventListenerManager.exportTalentsEmbed(document.querySelector(`div#midnight-skill-builder${this.idSuffix}`));
  }

  /**
   * Renders the class tree in embed mode.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing data about the trees.
   */
  drawClassTreeEmbed(tree) {
    const embedClassTree = document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="1"]`);
    if (!embedClassTree) return;

    embedClassTree.style.setProperty('--selected-spec-sprite', `url("${MidnightTalentCalculatorSpriteHelper.specSprite(tree.classData.name, tree.specData.name)}")`);

    let embedClassTreeHTML = `
      <div class="embed-container-header flex">${tree.classData.displayName} Talent Trees</div>
      <div class="embed-content-wrapper ${this.displayLevels ? `shown-points-by-level` : `hidden-points-by-level`}">
        <div class="embed-header flex">
          <div class="flex embed-icon-name">
            <div class="embed-class-icon-container">
              <div class="embed-class-icon icon_class_${tree.classData.name}"></div>
            </div>
            <div class="flex embed-name">View ${tree.classData.displayName} Talent Tree</div>
          </div>
          <svg width="24px" height="24px" viewBox="0 0 24 24" fill="#ffffff">
    `;

    if (this.collapseEmbed && !this.displayLevels) {
      embedClassTreeHTML += `<path d="M11.9999 13.1714L16.9497 8.22168L18.3639 9.63589L11.9999 15.9999L5.63599 9.63589L7.0502 8.22168L11.9999 13.1714Z"></path>`;
    } else if (!this.collapseEmbed || this.displayLevels) {
      embedClassTreeHTML += `<path d="M11.9999 10.8284L7.0502 15.7782L5.63599 14.364L11.9999 8L18.3639 14.364L16.9497 15.7782L11.9999 10.8284Z"></path>`;
    }

    embedClassTreeHTML += `
          </svg>          
        </div>
        <div class="embed-content-container ${tree.classData.name}-${tree.specData.name} flex">
    `;

    if (this.collapseEmbed && !this.displayLevels) {
      embedClassTreeHTML += `
        <div class="point-allocation-breakdown flex">
      `;
    } else if (!this.collapseEmbed || this.displayLevels) {
      embedClassTreeHTML += `
        <div class="point-allocation-breakdown flex active">
      `;
    }

    embedClassTreeHTML += `
            <div class="talents-by-points">
              <div class="skill-tree-points" style="background: #141e1c;">
                <p class="selected-name">points remaining:</p>
                <p class="spent-points"><span class="variable">${tree.classTree.spentPoints}</span>/<span>${tree.classTree.maxPoints}</span></p>
              </div>
              <div class="skill-point-attribution-list">
                <ul></ul>
              </div>            
            </div>
            <div class="talent-tree">
              <div class="skill-tree-lvl-required">
                <!--<p class="req-lvl">Level Required: <span id="classRequiredLvl">${tree.classTree.requiredLevel}</span></p>-->
                <div class="grid-container">
                  <div class="grid"></div>
                </div>
              </div>
            </div>
          </div>
    `;

    if (this.displayLevels) {
      embedClassTreeHTML += `
          <div class="embed-share-build-toggle flex">
            <div class="toggle-title">Talents by Level</div>
            <div class="button-container toggle active flex" title="Show Points by Level">
              <div class="toggle-box flex">ON</div>
            </div>
          </div>
      `;
    }

    embedClassTreeHTML += `
        </div>
      </div>
    `;

    embedClassTree.innerHTML = embedClassTreeHTML;
    this.drawClassTreeNodes(tree.classTree);
    this.talentCalculator.eventListenerManager.embedHeader(embedClassTree);
    if (this.displayLevels) this.talentCalculator.eventListenerManager.toggleTalentsByPoints(embedClassTree);
  }

  /**
   * Renders the spec tree in embed mode.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing data about the trees.
   */
  drawSpecTreeEmbed(tree) {
    const embedSpecTree = document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="2"]`);
    if (!embedSpecTree) return;

    embedSpecTree.style.setProperty('--selected-spec-sprite', `url("${MidnightTalentCalculatorSpriteHelper.specSprite(tree.classData.name, tree.specData.name)}")`);

    let embedSpecTreeHTML = `
      <div class="embed-content-wrapper ${this.displayLevels ? `shown-points-by-level` : `hidden-points-by-level`}">
        <div class="embed-header flex">
          <div class="flex embed-icon-name">
            <div class="embed-class-icon-container">
              <div class="embed-spec-icon bg-${tree.specData.icon}"></div>
            </div>
            <div class="flex embed-name">View ${tree.specData.displayName} Talent Tree</div>
          </div>
          <svg width="24px" height="24px" viewBox="0 0 24 24" fill="#ffffff">    
    `;

    if (this.collapseEmbed && !this.displayLevels) {
      embedSpecTreeHTML += `<path d="M11.9999 13.1714L16.9497 8.22168L18.3639 9.63589L11.9999 15.9999L5.63599 9.63589L7.0502 8.22168L11.9999 13.1714Z"></path>`;
    } else if (!this.collapseEmbed || this.displayLevels) {
      embedSpecTreeHTML += `<path d="M11.9999 10.8284L7.0502 15.7782L5.63599 14.364L11.9999 8L18.3639 14.364L16.9497 15.7782L11.9999 10.8284Z"></path>`;
    }

    embedSpecTreeHTML += `
        </svg>          
      </div>
      <div class="embed-content-container ${tree.classData.name}-${tree.specData.name} flex">
    `;

    if (this.collapseEmbed && !this.displayLevels) {
      embedSpecTreeHTML += `<div class="point-allocation-breakdown spec flex">`;
    } else if (!this.collapseEmbed || this.displayLevels) {
      embedSpecTreeHTML += `<div class="point-allocation-breakdown spec flex active">`;
    }

    embedSpecTreeHTML += `
            <div class="talents-by-points">
              <div class="skill-tree-points" style="background: #141e1c;">
                <p class="selected-name">points remaining:</p>
                <p class="spent-points"><span class="variable">${tree.specTree.spentPoints}</span>/<span>${tree.specTree.maxPoints}</span></p>
              </div>
              <div class="skill-point-attribution-list">
                <ul></ul>
              </div>
            </div>
            <div class="talent-tree">
              <div class="skill-tree-lvl-required">
                <!--<p class="req-lvl">Level Required: <span id="classRequiredLvl">${tree.specTree.requiredLevel}</span></p>-->
                <div class="grid-container">
                  <div class="grid"></div>
                </div>
              </div>
            </div>
          </div>
    `;

    if (this.displayLevels) {
      embedSpecTreeHTML += `
          <div class="embed-share-build-toggle flex">
            <div class="toggle-title">Talents by Level</div>
            <div class="button-container toggle active flex" title="Show Points by Level">
              <div class="toggle-box flex">ON</div>
            </div>
          </div>      
      `;
    }

    embedSpecTreeHTML += `
        </div>
      </div>    
    `;

    embedSpecTree.innerHTML = embedSpecTreeHTML;
    this.drawSpecTreeNodes(tree.specTree);
    this.talentCalculator.eventListenerManager.embedHeader(embedSpecTree);
    this.talentCalculator.eventListenerManager.toggleTalentsByPoints(embedSpecTree);
  }

  /**
   * Renders the hero tree in embed mode.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing data about the trees.
   */
  drawHeroTreeEmbed(tree) {
    const embedHeroTree = document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="3"]`);
    embedHeroTree.style.setProperty('--selected-spec-sprite', `url("${MidnightTalentCalculatorSpriteHelper.specSprite(tree.classData.name, tree.specData.name)}")`);
    embedHeroTree.style.setProperty('--selected-hero-sprite', `url("${MidnightTalentCalculatorSpriteHelper.heroSprite(tree.classData.name)}")`);

    let embedHeroTreeHTML = `
      <div class="embed-content-wrapper ${this.displayLevels ? `shown-points-by-level` : `hidden-points-by-level`}">
    `;

    if (!this.heroOnly) {
      embedHeroTreeHTML += `
        <div class="embed-header flex">
          <div class="flex embed-icon-name">
            <div class="embed-hero-icon-container">
              <div class="embed-hero-icon ${tree.classData.name}-${tree.heroTree.icon}" style="background-image: var(--selected-hero-sprite);"></div>
            </div>
            <div class="flex embed-name">View Hero Talent Tree</div>
          </div>
          <svg width="24px" height="24px" viewBox="0 0 24 24" fill="#ffffff">
      `;

      if (this.collapseEmbed && !this.displayLevels) {
        embedHeroTreeHTML += `<path d="M11.9999 13.1714L16.9497 8.22168L18.3639 9.63589L11.9999 15.9999L5.63599 9.63589L7.0502 8.22168L11.9999 13.1714Z"></path>`;
      } else if (!this.collapseEmbed || this.displayLevels) {
        embedHeroTreeHTML += `<path d="M11.9999 10.8284L7.0502 15.7782L5.63599 14.364L11.9999 8L18.3639 14.364L16.9497 15.7782L11.9999 10.8284Z"></path>`;
      }

      embedHeroTreeHTML += `
          </svg>      
        </div>
      `;
    }

    embedHeroTreeHTML += `
        <div class="embed-content-container ${tree.classData.name}-${tree.specData.name} flex">
    `;

    if (this.collapseEmbed && !this.displayLevels) {
      embedHeroTreeHTML += `
        <div class="point-allocation-breakdown flex">
      `;
    } else if (!this.collapseEmbed || this.displayLevels) {
      embedHeroTreeHTML += `
        <div class="point-allocation-breakdown flex active">
      `;
    }

    embedHeroTreeHTML += `
            <div class="talents-by-points">
              <div class="skill-tree-points" style="background: #141e1c;">
                <p class="selected-name">points remaining:</p>
                <p class="spent-points"><span class="variable">${tree.heroTree.spentPoints}</span>/<span>${tree.heroTree.maxPoints}</span></p>
              </div>
              <div class="skill-point-attribution-list">
                <ul></ul>
              </div>
            </div>
            <div class="talent-tree">
              <div class="skill-tree-lvl-required">
                <!--${this.heroOnly ? `<div style="height: 30px;"></div>` : `<p class="req-lvl">Level Required: <span id="classRequiredLvl">${tree.heroTree.requiredLevel}</span></p>`}-->
                <div class="grid-container">
                  <div class="grid"></div>
                </div>
              </div>
            </div>
          </div>
    `;

    if (this.displayLevels) {
      embedHeroTreeHTML += `
        <div class="embed-share-build-toggle flex">
          <div class="toggle-title">Talents by Level</div>
          <div class="button-container toggle active flex" title="Show Points by Level">
            <div class="toggle-box flex">ON</div>
          </div>
        </div>             
      `;
    }

    embedHeroTreeHTML += `
        </div>    
      </div>
    `;

    embedHeroTree.innerHTML = embedHeroTreeHTML;
    this.drawHeroTreeNodes(tree);

    this.talentCalculator.eventListenerManager.embedHeader(embedHeroTree);
    this.talentCalculator.eventListenerManager.toggleTalentsByPoints(embedHeroTree);

    const _self = this;
    Object.values(tree.heroTree.nodes).forEach(node => {
      let nodeType = _self.getNodeType(node);

      if (nodeType == 'active' || nodeType == 'passive') this.talentCalculator.eventListenerManager.roundSquareNodeEmbed(node);
      else if (nodeType == 'choice') this.talentCalculator.eventListenerManager.choiceNodeEmbed(node);
    });
  }

  /**
   * Sets the class with the given id as selected.
   * 
   * @param {Number} classId Id of the selected class.
   */
  makeClassSelectedInClassSelectors(classId) {
    // Select active class if there is one and remove the active class from it
    let selectedClass = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.playable-class.selected`);
    if (selectedClass) {
      selectedClass.classList.remove("selected");
    }
    // Select class with classId and make it selected
    document.querySelector(`#midnight-skill-builder${this.idSuffix} div.playable-class[data-class-id="${classId}"]`).classList.add("selected");
  }

  /**
   * Asynchronous method. Renders a selection interface granting the ability to select a specialization.
   * 
   * @param {Number} classId Id of the selected class. This determines what specs can be selected.
   */
  async drawSpecSelectors(classId) {
    // Disable the active spec selector, if any
    let activeSpecSelector = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.playable-spec-selection.active`)
    if (activeSpecSelector) {
      activeSpecSelector.classList.remove("active");
    }
    // Remove ``selected'' class from the previously selected spec.
    let selectedSpec = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.playable-spec.selected`);
    if (selectedSpec) {
      selectedSpec.classList.remove("selected");
    }
    // Check if spec selectors already exist for this class
    let specSelector = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.playable-spec-selection[data-associated-id="${classId}"]`)
    if (specSelector) {
      // Spec selector already exists, if it is already active, then do nothing, if it is not
      // then make it active.
      if (!specSelector.classList.contains("active")) {
        specSelector.classList.add("active");
      }
    } else {
      specSelector = `
          <div class="playable-spec-selection flex active" data-associated-id="${classId}">
`
      let json = await MidnightTalentCalculatorJSON.get("classes_basic_info");
      let classData = json.find((cd) => cd.id == classId);
      classData.specializations.forEach((specData) => {
        specSelector += `
            <div class="playable-spec flex direction-column" data-class-id="${specData.id}">
                <div class="playable-spec-icon">
                    <div class="spec-icon bg-${specData.icon}"></div>
                </div>
                <p class="spec-name">${specData.displayName}</p>
            </div>
`
      });
      specSelector += `
          </div>
`
      let classSelectors = document.querySelector(`#midnight-skill-builder${this.idSuffix} .playable-class-selection`);
      classSelectors.insertAdjacentHTML('afterend', specSelector);
      classData.specializations.forEach((specData) => {
        this.talentCalculator.eventListenerManager.specSelector(specData.id);
      });
    }
  }

  /**
   * Sets the specialization with the given id as selected.
   * 
   * @param {Number} specId Id of the selected specialization.
   */
  makeSpecSelectedInSpecSelectors(specId) {
    // Select selected spec if there is one and remove the selected class from it
    let selectedSpec = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.playable-spec.selected`);
    if (selectedSpec) {
      selectedSpec.classList.remove("selected");
    }
    // Select class with classId and make it active
    document.querySelector(`#midnight-skill-builder${this.idSuffix} div.playable-spec[data-class-id="${specId}"]`).classList.add("selected");
  }

  /**
   * Asynchronous function. Generates the HTML for hero tree selectors.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing information about the trees.
   */
  async drawHeroSelectors(tree) {
    let json = await MidnightTalentCalculatorJSON.get(`${tree.classData.name}`);
    let heroData = json.specs[tree.specData.name].hero;

    let heroSelector = `<div class="hero-talent-selection flex" data-associated-spec-id="${tree.specData.id}">`;
    ["left", "right"].forEach(position => {
      let heroTree = heroData[position];
      heroSelector += `
        <div class="hero-talent flex direction-column">
          <div class="hero-talent-icon ${tree.classData.name}-${heroTree.icon}" style="background-image: var(--selected-hero-sprite)"></div>
          <div class="hero-talent-name">${heroTree.name}</div>
        </div>
      `;      
    });
    heroSelector += `</div>`;

    document.getElementById(`heroTree${this.idSuffix}`).insertAdjacentHTML('afterbegin', heroSelector);
    this.talentCalculator.eventListenerManager.heroSelector();
  }

  /**
   * Generates HTMl for the base structure of the trees.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing data about the selected class/speialization/hero talent and their trees.
   */
  drawTree(tree) {
    document.querySelector(`#midnight-skill-builder${this.idSuffix} .skill-tree-container`)?.remove(); // If there is an existing skill-tree-container remove it before rendering the next tree.
    

    let treeHTML = `
<div id="skillTreeContainer${this.idSuffix}" class="skill-tree-container spec-selected ${tree.classData.name}-${tree.specData.name}"
  style="--selected-spec-color: #; --selected-spec-bg: url(&quot;${this.imagePath}/${tree.classData.name}_${tree.specData.name}-bg.webp&quot;);
         --selected-spec-bg-left: url(&quot;${this.imagePath}/${tree.classData.name}_${tree.specData.name}-bg-left.webp&quot;);
         --selected-spec-bg-right: url(&quot;${this.imagePath}/${tree.classData.name}_${tree.specData.name}-bg-right.webp&quot;);
         --selected-hero-sprite: url(&quot;${MidnightTalentCalculatorSpriteHelper.heroSprite(tree.classData.name)}&quot;);
         --selected-spec-sprite: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(tree.classData.name, tree.specData.name)}&quot;);">
                <div class="treesContainer flex">
                    <div id="classTree${this.idSuffix}" class="class-tree skill-tree-panel">
                        <div class="skill-tree-info flex">
                            <div class="selected-icon-name">
                                <div class="selected-icon">
                                    <div class="icon_class_${tree.classData.icon}"></div>
                                </div>
                                <p class="selected-name">
                                    <span>${tree.classData.displayName}</span> <em>tree</em>
                                </p>
                            </div>
                            <div class="req-lvl-rs">
                                <p class="req-lvl">Level Required: <span class="class-required-level">${tree.classTree.requiredLevel}</span></p>
                                <button id="rsC${this.idSuffix}" class="reset-class user-action-button reset">
                                    <svg width="16px" height="16px" viewBox="0 0 24 24">
                                        <path d="M5.46257 4.43262C7.21556 2.91688 9.5007 2 12 2C17.5228 2 22 6.47715 22 12C22 14.1361 21.3302 16.1158 20.1892 17.7406L17 12H20C20 7.58172 16.4183 4 12 4C9.84982 4 7.89777 4.84827 6.46023 6.22842L5.46257 4.43262ZM18.5374 19.5674C16.7844 21.0831 14.4993 22 12 22C6.47715 22 2 17.5228 2 12C2 9.86386 2.66979 7.88416 3.8108 6.25944L7 12H4C4 16.4183 7.58172 20 12 20C14.1502 20 16.1022 19.1517 17.5398 17.7716L18.5374 19.5674Z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="grid-container">
                            <div class="grid">
                            </div>
                        </div>
                    </div>
                    <div id="specTree${this.idSuffix}" class="spec-tree skill-tree-panel">
                        <div class="skill-tree-info flex">
                            <div class="selected-icon-name">
                                <div class="selected-icon">
                                    <div class="bg-${tree.specData.icon}"></div>
                                </div>
                                <p class="selected-name">
                                    <span>${tree.specData.displayName}</span> <em>tree</em>
                                </p>
                            </div>
                            <div class="req-lvl-rs">
                                <p class="req-lvl">Level Required: <span class="spec-required-level"">${tree.specTree.requiredLevel}</span></p>
                                <button id="rsS${this.idSuffix}" class="reset-spec user-action-button reset">
                                    <svg width="16px" height="16px" viewBox="0 0 24 24">
                                        <path d="M5.46257 4.43262C7.21556 2.91688 9.5007 2 12 2C17.5228 2 22 6.47715 22 12C22 14.1361 21.3302 16.1158 20.1892 17.7406L17 12H20C20 7.58172 16.4183 4 12 4C9.84982 4 7.89777 4.84827 6.46023 6.22842L5.46257 4.43262ZM18.5374 19.5674C16.7844 21.0831 14.4993 22 12 22C6.47715 22 2 17.5228 2 12C2 9.86386 2.66979 7.88416 3.8108 6.25944L7 12H4C4 16.4183 7.58172 20 12 20C14.1502 20 16.1022 19.1517 17.5398 17.7716L18.5374 19.5674Z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="grid-container">
                            <div class="grid">
                            </div>                            
                        </div>
                    </div>
                    <div id="heroTree${this.idSuffix}" class="hero-tree hero-tree-panel">
                        <div class="hero-talent-cell flex ">
                            <div class="hero-talent-container">
                                <div class="hero-talent">
                                    <div class="hero-talent-icon" style="background-image: var(--selected-hero-sprite)"></div>
                                </div>
                            </div>                          
                        </div>
                        <div class="grid-container">
                            <div class="grid">
                            </div>
                        </div>
                    </div>
                </div>
                <div id="pvp-talents-container${this.idSuffix}" class="pvp-talents-container">
                <div class="flex">
                    <div class="export-open-container">
                        <div class="buttons-container">`;

    if (!this.embed) {
      treeHTML += `
            <button class="user-action-button import-talent-tree">
                <svg width="16px" height="16px" viewbox="0 0 24 24" fill="#ffffff">
                  <path d="M21 3H3C2.44772 3 2 3.44772 2 4V20C2 20.5523 2.44772 21 3 21H21C21.5523 21 22 20.5523 22 20V4C22 3.44772 21.5523 3 21 3ZM12 16C10.3431 16 9 14.6569 9 13H4V5H20V13H15C15 14.6569 13.6569 16 12 16ZM16 9H13V6H11V9H8L12 13.5L16 9Z"></path>
                </svg>
                <p>Import Talents</p>
            </button>
      `;
    } 
    treeHTML += `
            <button class="user-action-button export-talents">
                <svg width="16px" height="16px" viewBox="0 0 24 24" fill="#ffffff">
                    <path d="M6.9998 6V3C6.9998 2.44772 7.44752 2 7.9998 2H19.9998C20.5521 2 20.9998 2.44772 20.9998 3V17C20.9998 17.5523 20.5521 18 19.9998 18H16.9998V20.9991C16.9998 21.5519 16.5499 22 15.993 22H4.00666C3.45059 22 3 21.5554 3 20.9991L3.0026 7.00087C3.0027 6.44811 3.45264 6 4.00942 6H6.9998ZM8.9998 6H16.9998V16H18.9998V4H8.9998V6Z"></path>
                </svg>
                <p>Export Talents</p>
            </button>
    `;
    
    if (this.embed) {
      treeHTML += `
            <button class="user-action-button open-in-calc">
                <svg width="16px" height="16px" viewBox="0 0 24 24" fill="#ffffff">
                    <path d="M4 2H20C20.5523 2 21 2.44772 21 3V21C21 21.5523 20.5523 22 20 22H4C3.44772 22 3 21.5523 3 21V3C3 2.44772 3.44772 2 4 2ZM7 12V14H9V12H7ZM7 16V18H9V16H7ZM11 12V14H13V12H11ZM11 16V18H13V16H11ZM15 12V18H17V12H15ZM7 6V10H17V6H7Z"></path>
                </svg>
                <p>Open in Calculator</p>
            </button>
      `;
    }

    treeHTML += `
                </div>
            </div>
                    <div class="pvp-slot-section flex">
                        <div class="pvp-talents-section flex">
                            <h3>PvP Talents:</h3>
                        <div class="pvp-slot-container flex" data-index="0">
                            <div class="pvp-slot-border">
                                <div class="pvp-slot-inner"></div>
                            </div>
                        </div>
                        <div class="pvp-slot-container flex" data-index="1">
                            <div class="pvp-slot-border">
                                <div class="pvp-slot-inner"></div>
                            </div>
                        </div>
                        <div class="pvp-slot-container flex" data-index="2">
                            <div class="pvp-slot-border">
                                <div class="pvp-slot-inner"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>
    `;

    if (this.embed) {
      let skillBuilder = document.getElementById(`midnight-skill-builder${this.idSuffix}`);
      skillBuilder.innerHTML = treeHTML;
      this.talentCalculator.eventListenerManager.exportTalentsButton();
    } else {
      let specSelectors = document.querySelectorAll(`#midnight-skill-builder${this.idSuffix} .playable-spec-selection`);
      specSelectors[specSelectors.length-1].insertAdjacentHTML('afterend', treeHTML);

      this.drawPvpTalentsPopup(tree);
      this.talentCalculator.eventListenerManager.resetButtons();
      this.talentCalculator.eventListenerManager.importStringButton();
      this.talentCalculator.eventListenerManager.exportTalentsButton();
    }
  }

  /**
   * Generates the HTML for the pvp talents popup. 
   * 
   * @param {MidnightTalentCalculatorTree} tree The tree object containing information about the pvp talents.
   */
  drawPvpTalentsPopup(tree) {
    const pvpTalentsContainer = document.querySelector(`#midnight-skill-builder${this.idSuffix} #pvp-talents-container${this.idSuffix} div.flex`);
    let pvpTalentsPopupHTML = `
      <div class="pvp-talents-pop-up">
        <div class="pvp-talents-pop-up-content flex">
          <div class="pvp-talents-list-header flex">
            <span class="title">Select Talent</span>
            <button class="remove-current-talent user-action-button">Remove Current Talent</button>
            <button class="close-pvp-talents-list flex">+</button>
          </div>
          <ul class="pvp-talents-list">
    `;

    Object.values(tree.pvpTalents).forEach(pvpTalent => {
      pvpTalentsPopupHTML += `
              <li class="pvp-talent-list-entry flex" data-pvp-talent-id="${pvpTalent.id}">
                <div class="pvp-talent-icon-container">
                  <div class="pvp-talent-icon ${tree.classData.name}_${tree.specData.name}-${pvpTalent.icon}" style="background-image: var(--selected-spec-sprite)"></div>
                </div>
                <div class="pvp-talent-text flex">
                  <span class="pvp-talent-name">${pvpTalent.name}</span>
                  <span class="pvp-talent-description">${pvpTalent.description}</span>
                </div>
              </li>
      `;
    });

    pvpTalentsPopupHTML += `
          </ul>
        </div>
      </div>
    `;

    pvpTalentsContainer.insertAdjacentHTML('beforeend', pvpTalentsPopupHTML);
    this.talentCalculator.eventListenerManager.pvpTalentsSelector();
    this.talentCalculator.eventListenerManager.pvpTalentsPopupHeader();
  }

  /**
   * Generates HTML for both class tree and spec tree.
   * 
   * @param {ClassTree|SpecTree|HeroTree} tree Object containing the data of the trees.
   */
  drawNodes(tree) {
    this.drawClassTreeNodes(tree.classTree);
    this.drawSpecTreeNodes(tree.specTree);
  }

  /**
   * Returns the spell associated to the node depending on the node type.
   * 
   * @param {ChoiceNode|RoundNode|SquareNode|undefined} node The node whose spells we want.
   * @returns {Object|Array<Object>} Either a single spell object or an array of spell objects (when the node is a choice node).
   */
  getNodeSpells(node) {
    if (node.spells.length == 1) {
      return node.spells[0];
    } else if (node.spells.length > 1) {
      return node.spells;
    } else console.log(`Undefined spell for node ${node.id}!`);
  }

  /**
   * Returns the type of incoming node.
   * 
   * @param {ChoiceNode|RoundNode|SquareNode} node The node whose type we want.
   * @returns {string} Type of the node.
   */
  getNodeType(node) {
    if (node instanceof MidnightTalentCalculatorNodeChoice) return 'choice';
    else if (node instanceof MidnightTalentCalculatorNodeRound) return 'passive';
    else if (node instanceof MidnightTalentCalculatorNodeSquare) return 'active';
    else console.log(`Unknown node type: ${node.constructor.name}`);
  }

  /**
   * Deselects a selected choice node skill.
   * 
   * @param {ChoiceNode} node The choice node whose skill is being deselected.
   */
  deselectChoiceNodeSkill(node) {
    Array.from(document.querySelector(`#midnight-skill-builder${this.idSuffix} div.skill-cell[data-node-id="${node.id}"] div.skill-icon`).children).forEach(skill => {
      skill.classList.remove('selected');
    });
  }

  /**
   * Updates the given node.
   * 
   * @param {ChoiceNode|SquareNode|RoundNode} node The node that is to be updated.
   * @param {string} [choiceId = null] An optional parameter to specify the chosen talent.
   */
  refreshNode(node, choiceId = null) {
    const nodeHTML = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.skill-cell[data-node-id="${node.id}"]`);
    if (!nodeHTML) return;

    const _self = this;

    if (node.state == 'active') {
      nodeHTML.classList.add('available-for-picking');
      nodeHTML.classList.remove('maxed-out'); // If node.state was previously 'maxedOut' and a point has beed removed from node remove also the maxed-out status.
    }
    if (node.state == 'maxedOut') nodeHTML.classList.add('maxed-out');
    if (node.state == 'inactive') {
      nodeHTML.classList.remove('maxed-out');
      nodeHTML.classList.remove('available-for-picking');
    }
    if (choiceId != null && _self.getNodeType(node) == 'choice') this.setChoiceActiveIcon(node, choiceId);
    nodeHTML.querySelector('div.skill-point-container span').innerHTML = node.currentPoints;
  }

  /**
   * Updates the given connection.
   * 
   * @param {MidnightTalentCalculatorConnection} connection 
   */
  refreshConnection(connection) {
    if (connection.state == 'active') this.makeConnectionActive(connection);
    else if (connection.state == 'inactive') this.makeConnectionInactive(connection);
  }

  /**
   * Updates the pvp talent slot  icon.
   * 
   * @param {Number} index The index of the pvp talent slot.
   */
  refreshPvpTalent(index) {
    const pvpSlotContainer = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.pvp-slot-container[data-index="${index}"]`)
    if (this.talentCalculator.tree.chosenPvPTalents[index]) {
      pvpSlotContainer.querySelector('div.pvp-talent-icon')?.remove();

      let pvpTalentIconHTML = `<div class="pvp-talent-icon ${this.talentCalculator.tree.classData.name}_${this.talentCalculator.tree.specData.name}-${this.talentCalculator.tree.chosenPvPTalents[index].icon}" data-pvp-talent-id="${this.talentCalculator.tree.chosenPvPTalents[index].id}" style="background-image: var(--selected-spec-sprite);"></div>`;
      pvpSlotContainer.querySelector('div.pvp-slot-inner').insertAdjacentHTML('beforeend', pvpTalentIconHTML);
    } else {
      pvpSlotContainer.querySelector('div.pvp-talent-icon')?.remove();
    }
    this.updateHash();
  }

  /**
   * Sets the icon of clicked on talent for the choice node.
   * 
   * @param {ChoiceNode|SquareNode|RoundNode} node The clicked on node.
   */
  setChoiceActiveIcon(node, choiceId) {
    this.deselectChoiceNodeOption(node);
    const choiceNodeIcons = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.skill-cell[data-node-id="${node.id}"] div.skill-icon`);
    if (choiceId == 0) choiceNodeIcons.querySelector('div.left-skill').classList.add('selected');
    if (choiceId == 1) choiceNodeIcons.querySelector('div.right-skill').classList.add('selected');
  }

  /**
   * Generates HTML representing a node depending on its type.
   * 
   * @param {ClassTree|SpecTree|HeroTree} tree The tree object containing data.
   * @param {string} nodeType The type of drawn node.
   * @param {ChoiceNode|RoundNode|SquareNode} node The node being drawn.
   * @param {string} treeType The tree type. ('spec'|'class'|'hero')
   * @returns {string} The HTML string representing the drawn node.
   */
  drawNode(tree, nodeType, node, treeType) {
    if (MIDNIGHTDEBUG) {
      console.log(`drawNode: entering ${node.toString()}`);
    }

    let classes = '';
    node.treeType = treeType;

    node.treeType = treeType;
    if (node.state == 'permanentlyMaxedOut') {
      classes += `default-spec-${tree.specData.name}`;
    }
    if (node.state == 'active') {
      classes += ' available-for-picking';
    }

    let nodeHTML = `
        <div class="skill-cell ${nodeType} ${classes}" data-row="${node.row + 1}" data-column="${node.column}" data-node-id="${node.id}" data-tooltip-id="${node.id}" data-tooltip-type="common" data-tree-type="${treeType}" data-max-points="${node.maxPoints}" data-node-type="${nodeType}">
          <div class="skill-container">
            <div class="skill ${nodeType}">
              <div class="clip-container">
      `;


    if (nodeType == 'passive' || nodeType == 'active') {
      nodeHTML += `
        <div class="skill-icon ${tree.classData.name}_${tree.specData.name}-${this.getNodeSpells(node)?.icon}" data-spell-id="${this.getNodeSpells(node)?.spellId}" style="background-image: var(--selected-spec-sprite)"></div>
      `;
    } else if (nodeType == 'choice') {
      const firstSpell = this.getNodeSpells(node)[0];
      const secondSpell = this.getNodeSpells(node)[1];      
      nodeHTML += `
        <div class="skill-icon">
          <div class="left-skill ${tree.classData.name}_${tree.specData.name}-${firstSpell.icon}" data-spell-id="${firstSpell.spellId}" data-index="0" style="background-image: var(--selected-spec-sprite)"></div>
          <div class="right-skill ${tree.classData.name}_${tree.specData.name}-${secondSpell.icon}" data-spell-id="${secondSpell.spellId}" data-index="1" style="background-image: var(--selected-spec-sprite)"></div>
        </div>
      `;
    }

    nodeHTML += `
            </div>
          </div>
          <div class="skill-point-container">
            <span>${node.currentPoints}</span>/${node.maxPoints}
          </div>
    `;

    if (nodeType == 'choice' && !this.embed) nodeHTML += this.drawChoicePopup(tree, node, treeType);

    nodeHTML += `
        </div>
      </div>
    `;

    return nodeHTML;
  }

  /**
   * Renders an element in the list of nodes.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing data about the tree.
   * @param {MidnightTalentCalculatorNode | MidnightTalentCalculatorApexTalent} node Node object containing data about the node.
   * @param {string} nodeType Type of the node.
   * @param {Number} lvl The current value of tree required level.
   * @param {string} treeType 'class'|'spec'|'hero'
   */
  drawNodeTalentByLevel(tree, node, nodeType, lvl, treeType, choice=null) {
    let nodeIcon = node.spells[0].icon;
    let nodeName = node.spells[0].name;

    if (choice) {
      nodeIcon = node.spells[choice].icon;
      nodeName = node.spells[choice].name;
    } else if (node instanceof MidnightTalentCalculatorApexTalent) {
      nodeIcon = node.icon;
      nodeName = node.name;
    }

    let nodeHTML = `
      <li class="list-spell flex" data-node-id="${node.id}">
        <div class="point-lvl">Lvl. ${lvl}</div>
        <div class="name-of-spell flex">
          <div class="sc-container ${nodeType}">
            <div class="sc skill ${nodeType}">
              <div class="skill-icon ${tree.classData.name}_${tree.specData.name}-${nodeIcon}" style="background-image: var(--selected-spec-sprite);"></div>
            </div>
          </div>
          <div class="name">${nodeName}</div>
        </div>
      </li>
    `;

    switch(treeType) {
      case 'class':
        document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="1"] div.talents-by-points ul`).insertAdjacentHTML('beforeend', nodeHTML);
        break;
      case 'spec':
        document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="2"] div.talents-by-points ul`).insertAdjacentHTML('beforeend', nodeHTML);
        break;
      case 'hero':
        document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="3"] div.talents-by-points ul`).insertAdjacentHTML('beforeend', nodeHTML);
        break;
      default:
        break;
    }
    
    this.talentCalculator.eventListenerManager.nodeTalentByLevel(node, choice);
  }

  /**
   * Sets the value in required level container to current subtree required level.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing data about the trees.
   * @param {string} treeType 'class'|'spec'|'hero'
   */
  setEmbedReqLvl(tree, treeType) {
    let embedTreeReqLvl = null;
    switch(treeType) {
      case 'class':
        embedTreeReqLvl = document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="1"] span#classRequiredLvl`);
        if (!embedTreeReqLvl) break;

        embedTreeReqLvl.innerHTML = tree.classTree.requiredLevel;
        break;
      case 'spec':
        embedTreeReqLvl = document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="2"] span#classRequiredLvl`);
        if (!embedTreeReqLvl) break;

        embedTreeReqLvl.innerHTML = tree.specTree.requiredLevel;
        break;
      case 'hero':
        embedTreeReqLvl = document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="3"] span#classRequiredLvl`);
        if (!embedTreeReqLvl) break;

        embedTreeReqLvl.innerHTML = tree.heroTree.requiredLevel;
        break;
      default:
        break;
    }
  }

  /**
   * Generates HTML for choice node popup.
   * 
   * @param {ClassTree|SpecTree|HeroTree} tree The tree object containing data.
   * @param {ChoiceNode|RoundNode|SquareNode} node The node being drawn.
   * @param {string} treeType The tree type. ('spec'|'class'|'hero')
   * @returns {string} The HTML string representing the drawn choice popup.
   */
  drawChoicePopup(tree, node, treeType) {
    const firstSpell = this.getNodeSpells(node)[0];
    const secondSpell = this.getNodeSpells(node)[1];
    const popupHTML = `
        <div class="choice-popup">
          <div class="option flex" data-choice-id="0" data-tooltip-id="${node.id}" data-tree-type="${treeType}" data-tooltip-type="${treeType}" data-tooltip-spell-index="0">
            <div class="choice-img-container">
              <div class="choice-img ${tree.classData.name}_${tree.specData.name}-${firstSpell.icon}" style="background-image: var(--selected-spec-sprite)"></div>
            </div>
            <span class="choice-name">${firstSpell.name}</span>
          </div>
          <div class="option flex" data-choice-id="1" data-tooltip-id="${node.id}" data-tree-type="${treeType}" data-tooltip-type="${treeType}" data-tooltip-spell-index="1">
            <div class="choice-img-container">
              <div class="choice-img ${tree.classData.name}_${tree.specData.name}-${secondSpell.icon}" style="background-image: var(--selected-spec-sprite)"></div>
            </div>
            <span class="choice-name">${secondSpell.name}</span>
          </div>
        </div>
    `;

    return popupHTML;
  }

  /**
   * Deselects the chose talent so that the user can choose between one of the options again.
   * 
   * @param {ChoiceNode} node 
   */
  deselectChoiceNodeOption(node) {
    Array.from(document.getElementById(`midnight-skill-builder${this.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"] div.skill-icon`).children).forEach(skill => {
      skill.classList.remove('selected');
    });
  }

  /**
   * Updates the tooltip of choice node. Called only when right clicked on activated choice node.
   * 
   * @param {ChoiceNode} node
   */
  updateChoiceNodeTooltip(node) {
    const tooltipContent = `
      <div class="content flex direction-column">
        <div class="spell">
          <div class="tooltip-icon-and-name flex">
            <div class="sprite ${node.subtree.classData.name}_${node.subtree.specData.name}-${node.spells[0].icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(node.subtree.tree.classData.name, node.subtree.tree.specData.name)}&quot;)"></div>
            <span class="tooltip-name">${node.spells[0].name}</span>
          </div>
          <div class="midnight-tooltip-description">${node.spells[0].rankDescriptions[node.currentPoints - 1] ?? node.spells[0].description}</div>
        </div>
        <div class="spell">
          <div class="tooltip-icon-and-name flex">
            <div class="sprite ${node.subtree.classData.name}_${node.subtree.specData.name}-${node.spells[1].icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(node.subtree.tree.classData.name, node.subtree.tree.specData.name)}&quot;)"></div>
            <span class="tooltip-name">${node.spells[1].name}</span>
          </div>
          <div class="midnight-tooltip-description">${node.spells[1].rankDescriptions[node.currentPoints - 1] ?? node.spells[1].description}</div>
        </div>
      </div>
    `;
    this.talentCalculator.tooltipContainer.innerHTML = tooltipContent;
  }

  updateApexTalentTooltip(specTree) {
    let tooltipContent = `
      <div class="content flex direction-column">
        <div class="spell">
          <div class="tooltip-icon-and-name flex">
            <div class="sprite ${specTree.tree.classData.name}_${specTree.specData.name}-${specTree.apexTalent.icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(specTree.tree.classData.name, specTree.tree.specData.name)}&quot;)"></div>
            <span class="tooltip-name">${specTree.apexTalent.name}</span>
          </div>
          ${specTree.apexTalent.spells.map(spell => {
            return `
              <div class="midnight-tooltip-description ${spell.usedPoints === 0 ? "inactive" : ""}">
                ${spell.usedPoints === 0 ? spell.rankDescriptions[0] : spell.rankDescriptions[spell.usedPoints - 1]}
              </div>
            `;
          }).join('<br>')}
        </div>
      </div>
    `;
    specTree.tree.talentCalculator.tooltipContainer.innerHTML = tooltipContent;
  }

  /**
   * Generates HTML for a connection between nodes.
   * 
   * @param {MidnightTalentCalculatorConnection} connection The connection that will be generated.
   * @returns {string} The HTML string representing the connection.
   */
  drawNodeConnection(connection) {
    let activeClass = '';
    const columnWidthPercentage = 100 / 18;
    const rowHeightPercentage = 100 / 10;

    if (connection.state == 'active') {
      activeClass = 'active';
    }
    return `  <line class="skill-connection ${activeClass}" data-start="${connection.srcNode.id}" data-end="${connection.dstNode.id}" x1="${columnWidthPercentage * connection.srcNode.column}%" y1="${(rowHeightPercentage * connection.srcNode.row) + 5}%" x2="${columnWidthPercentage * connection.dstNode.column}%" y2="${(rowHeightPercentage * connection.dstNode.row) + 5}%"></line>`;
  }

  /**
   * Generates HTML for a connection between nodes.
   * 
   * @param {MidnightTalentCalculatorConnection} connection The connection that will be generated.
   * @returns  {string} The HTML string representing the connection.
   */
  drawHeroTreeNodeConnection(connection) {
    let activeClass = '';
    const columnWidthPercentage = 100 / 8;
    const rowHeightPercentage = 100 / 5;

    if (connection.state == 'active') {
      activeClass = 'active';
    }
    return `  <line class="skill-connection ${activeClass}" data-start="${connection.srcNode.id}" data-end="${connection.dstNode.id}" x1="${columnWidthPercentage * connection.srcNode.column}%" y1="${(rowHeightPercentage * connection.srcNode.row) + 5}%" x2="${columnWidthPercentage * connection.dstNode.column}%" y2="${(rowHeightPercentage * connection.dstNode.row) + 5}%"></line>`;
  }

  /**
   * Generates HTML for tracking spent points in given tree.
   * 
   * @param {ClassTree|SpecTree|HeroTree} tree Object containing data of the tree.
   */
  drawSpentPointsContainer(tree) {
    return `<div class="flex spent-available-points"><span>${tree.spentPoints}</span>&nbsp;/&nbsp;${tree.maxPoints}</div>`;
  }

  /**
   * Updates the displayed spent points.
   * 
   * @param {ClassTree|SpecTree|HeroTree} subtree Object containing data of the tree.
   * @param {string} treeType The type of the tree {'spec'|'class'|'hero'}
   */
  refreshSpentPointsContainer(subtree, treeType) {
    if (!this.embed) {
      document.querySelector(`#midnight-skill-builder${this.idSuffix} .${treeType}-tree div.spent-available-points span`).innerHTML = `${subtree.spentPoints}`;
    }
  }

  /**
   * Updates the displayed required level value.
   * 
   * @param {ClassTree|SpecTree|HeroTree} subtree Object containing data of the tree.
   * @param {string} treeType The type of the tree {'spec'|'class'|'hero'}
   */
  refreshRequiredLevelContainer(subtree, treeType) {
    if (!this.embed) {
      document.querySelector(`#midnight-skill-builder${this.idSuffix} .${treeType}-required-level`).innerHTML = `${subtree.requiredLevel}`;
    }
  };

  /**
   * Generates HTML for the class tree. nodes
   * 
   * @param {MidnightTalentCalculatorClassTree} classTree The object containing information about the tree.
   */
  drawClassTreeNodes(classTree) {
    if (MIDNIGHTDEBUG) {
      console.log(`drawClassTreeNodes: entering`);
    }
    const _self = this;
    let classTreeHTML = '';
    let classTreeTalentByLevelHTML = '';
    let classTreeConnectionsHTML = `<svg class="tree-connections" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">`;
    let firstDemarcatorRowMostLeftNode = null;
    let secondDemarcatorRowMostLeftNode = null;

    Object.values(classTree.nodes).forEach(node => {
      let nodeType = _self.getNodeType(node);
      
      if (node.row == classTree.checkpoints[0].row && !this.embed) {
        if (!firstDemarcatorRowMostLeftNode) firstDemarcatorRowMostLeftNode = node.column;
        else if (node.column < firstDemarcatorRowMostLeftNode) firstDemarcatorRowMostLeftNode = node.column;
      }
      if (node.row == classTree.checkpoints[1].row && !this.embed) {
        if (!secondDemarcatorRowMostLeftNode) secondDemarcatorRowMostLeftNode = node.column;
        else if (node.column < secondDemarcatorRowMostLeftNode) secondDemarcatorRowMostLeftNode = node.column;
      }

      classTreeHTML += this.drawNode(classTree, nodeType, node, 'class');
    });

    Object.values(classTree.connections).forEach(connection => {
      classTreeConnectionsHTML += this.drawNodeConnection(connection);
    });

    classTreeConnectionsHTML += '</svg>';
    if (!this.embed) {
      let classTreeGrid = document.getElementById(`classTree${this.idSuffix}`).querySelector('.grid-container').querySelector('.grid');
      classTreeGrid.insertAdjacentHTML('afterbegin', classTreeHTML);
      Object.values(classTree.nodes).forEach(node => {
        let nodeType = _self.getNodeType(node);

        if (nodeType == 'active' || nodeType == 'passive') this.talentCalculator.eventListenerManager.roundSquareNodeActivation(node, 'class');
        else if (nodeType == 'choice') this.talentCalculator.eventListenerManager.choiceNodeActivation(node, 'class');
      });
      classTreeGrid.insertAdjacentHTML('beforeend', classTreeConnectionsHTML);
      classTreeGrid.insertAdjacentHTML('beforebegin', this.drawSpentPointsContainer(classTree));
      this.drawDemarcators(classTree.checkpoints, classTreeGrid, firstDemarcatorRowMostLeftNode, secondDemarcatorRowMostLeftNode);
    } else {
      let embedClassTreeGrid = document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="1"] div.grid`);
      if (!embedClassTreeGrid) return;

      embedClassTreeGrid.insertAdjacentHTML('afterbegin', classTreeHTML);
      embedClassTreeGrid.insertAdjacentHTML('beforeend', classTreeConnectionsHTML);
      Object.values(classTree.nodes).forEach(node => {
        let nodeType = _self.getNodeType(node);

        if (nodeType == 'active' || nodeType == 'passive') this.talentCalculator.eventListenerManager.roundSquareNodeEmbed(node);
        else if (nodeType == 'choice') this.talentCalculator.eventListenerManager.choiceNodeEmbed(node);
      });
    }

    if (MIDNIGHTDEBUG) {
      console.log(`drawClassTreeNodes: exiting`);
    }
  }

  drawClassTreeNodesTalentByLevel(classTree) {
    
  }

  /**
   * Generates HTML for the spec tree nodes.
   * 
   * @param {MidnightTalentCalculatorSpecTree} specTree The object containing information about the tree.
   */
  drawSpecTreeNodes(specTree) {
    const _self = this;
    let specTreeHTML = '';
    let specTreeHTMLTalentByLevel = '';
    let specTreeConnectionsHTML = `<svg class="tree-connections" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">`;
    let firstDemarcatorRowMostLeftNode = null;
    let secondDemarcatorRowMostLeftNode = null;

    Object.values(specTree.nodes).forEach(node => {
      let nodeType = _self.getNodeType(node);

      if (node.row == specTree.checkpoints[0].row && !this.embed) {
        if (!firstDemarcatorRowMostLeftNode) firstDemarcatorRowMostLeftNode = node.column;
        else if (node.column < firstDemarcatorRowMostLeftNode) firstDemarcatorRowMostLeftNode = node.column;
      }
      if (node.row == specTree.checkpoints[1].row && !this.embed) {
        if (!secondDemarcatorRowMostLeftNode) secondDemarcatorRowMostLeftNode = node.column;
        else if (node.column < secondDemarcatorRowMostLeftNode) secondDemarcatorRowMostLeftNode = node.column;
      }

      specTreeHTML += this.drawNode(specTree, nodeType, node, 'spec');
    });

    Object.values(specTree.connections).forEach(connection => {
      specTreeConnectionsHTML += this.drawNodeConnection(connection);
    });

    specTreeConnectionsHTML += '</svg>';
    if (!this.embed) {
      let specTreeGrid = document.getElementById(`specTree${this.idSuffix}`).querySelector('.grid-container').querySelector('.grid');
      specTreeGrid.insertAdjacentHTML('afterbegin', specTreeHTML);
      Object.values(specTree.nodes).forEach(node => {
        let nodeType = _self.getNodeType(node);
  
        if (nodeType == 'active' || nodeType == 'passive') this.talentCalculator.eventListenerManager.roundSquareNodeActivation(node);
        else if (nodeType == 'choice') this.talentCalculator.eventListenerManager.choiceNodeActivation(node);
      });
      /* TODO: add this back in when spell icon is available
            <div class="apex-talent-spells">
              ${specTree.apexTalent.spells.map(spell => `<div class="spell">${spell.usedPoints}</div>`).join('')}
            </div>
      */
      const apexTalentHTML = `
        <div class="apex-talent-container">
          <div class="apex-talent">
            <div class="apex-talent-icon ${specTree.tree.classData.name}_${specTree.specData.name}-${specTree.apexTalent.icon}" style="background-image: var(--selected-spec-sprite)"></div>
            <div class="apex-talent-points">${specTree.apexTalent.usedPoints}/${specTree.apexTalent.maxPoints}</div>
          </div>
        </div>
      `;
      specTreeGrid.insertAdjacentHTML('beforeend', specTreeConnectionsHTML);
      specTreeGrid.insertAdjacentHTML('beforebegin', this.drawSpentPointsContainer(specTree));
      specTreeGrid.insertAdjacentHTML('afterend', apexTalentHTML);
      this.updateApexTalentIcon(specTree);
      this.drawDemarcators(specTree.checkpoints, specTreeGrid, firstDemarcatorRowMostLeftNode, secondDemarcatorRowMostLeftNode);
      this.talentCalculator.eventListenerManager.apexTalent(specTree);
      this.talentCalculator.eventListenerManager.apexTalentTooltip(specTree);
    } else {
      let embedSpecTreeGrid = document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="2"] div.grid`);
      if (!embedSpecTreeGrid) return;

      /* TODO: add this back in when spell icon is available
            <div class="apex-talent-spells">
              ${specTree.apexTalent.spells.map(spell => `<div class="spell">${spell.usedPoints}</div>`).join('')}
            </div>
      */
      const apexTalentHTML = `
        <div class="apex-talent-container">
          <div class="apex-talent">
            <div class="apex-talent-icon ${specTree.tree.classData.name}_${specTree.specData.name}-${specTree.apexTalent.icon}" style="background-image: var(--selected-spec-sprite)"></div>
            <div class="apex-talent-points">${specTree.apexTalent.usedPoints}/${specTree.apexTalent.maxPoints}</div>
          </div>
        </div>
      `;

      embedSpecTreeGrid.insertAdjacentHTML('afterbegin', specTreeHTML);
      embedSpecTreeGrid.insertAdjacentHTML('beforeend', specTreeConnectionsHTML);    
      embedSpecTreeGrid.insertAdjacentHTML('afterend', apexTalentHTML);
      this.updateApexTalentIcon(specTree);
      this.talentCalculator.eventListenerManager.apexTalentTooltip(specTree);
      Object.values(specTree.nodes).forEach(node => {
        let nodeType = _self.getNodeType(node);

        if (nodeType == 'active' || nodeType == 'passive') this.talentCalculator.eventListenerManager.roundSquareNodeEmbed(node);
        else if (nodeType == 'choice') this.talentCalculator.eventListenerManager.choiceNodeEmbed(node);
      });
    }
  }

  /**
   * Generates the HTML for the demarcators in individual trees.
   * 
   * @param {Array<Object>} checkpoints An array of Objects containing data about the checkpoints.
   * @param {HTMLDivElement} grid The element containing the rendered tree.
   * @param {Number} firstCheckpointNode The column of the node to which the first checkpoint will go.
   * @param {Number} secondCheckpointNode The column of the node to which the second checkpoint will go.
   */
  drawDemarcators(checkpoints, grid, firstCheckpointNode, secondCheckpointNode) {
    let treeDemarcatorsHTML = '';
    let counter = 1;
    for (const checkpoint of checkpoints) {
      treeDemarcatorsHTML += `
        <div class="points-demarcator flex direction-column p${counter}" style="--checkpoint-top: ${(counter == 1 && firstCheckpointNode == 1) || (counter == 2 && secondCheckpointNode == 1) ? (checkpoint.row * 10) : (checkpoint.row * 10) + 5}%">
      `;
      if (counter == 1) {
        treeDemarcatorsHTML += `
          <div style="--checkpoint-width: ${firstCheckpointNode == 1 ? (firstCheckpointNode * (100 / 18)) : (firstCheckpointNode * (100 / 18)) - (100/18)}%"></div>
          <p first-break-point>${checkpoint.points}</p>
        `;
      } else {
        treeDemarcatorsHTML += `
          <div style="--checkpoint-width: ${secondCheckpointNode == 1 ? (secondCheckpointNode * (100 / 18)) : (secondCheckpointNode * (100 / 18)) - (100/18)}%"></div>
          <p second-break-point>${checkpoint.points}</p>
        `;
      }

      treeDemarcatorsHTML += `
          <svg width="24px" height="24px" viewBox="0 0 24 24" fill="var(--points-demarcator-line-color)">
            <path d="M19 10H20C20.5523 10 21 10.4477 21 11V21C21 21.5523 20.5523 22 20 22H4C3.44772 22 3 21.5523 3 21V11C3 10.4477 3.44772 10 4 10H5V9C5 5.13401 8.13401 2 12 2C15.866 2 19 5.13401 19 9V10ZM17 10V9C17 6.23858 14.7614 4 12 4C9.23858 4 7 6.23858 7 9V10H17ZM11 14V18H13V14H11Z"></path>
          </svg>
        </div>
      `;
      counter++;
    }

    grid.insertAdjacentHTML('beforeend', treeDemarcatorsHTML);
  }

  /**
   * Generates the HTMl for the hero tree's required level container.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing information about the trees.
   */
  drawHeroTreeLvlRequired(tree) {
    const heroTalentCell = document.querySelector(`#midnight-skill-builder${this.idSuffix} #heroTree${this.idSuffix}`);
    heroTalentCell.insertAdjacentHTML('beforeend', `
      <div class="req-lvl-container flex">
        <p class="req-lvl">Level Required: <span class="hero-required-level">${tree.heroTree.requiredLevel}</span></p>
        <button id="rsH${this.idSuffix}" class="user-action-button reset">
          <svg width="16px" height="16px" viewBox="0 0 24 24">
            <path d="M5.46257 4.43262C7.21556 2.91688 9.5007 2 12 2C17.5228 2 22 6.47715 22 12C22 14.1361 21.3302 16.1158 20.1892 17.7406L17 12H20C20 7.58172 16.4183 4 12 4C9.84982 4 7.89777 4.84827 6.46023 6.22842L5.46257 4.43262ZM18.5374 19.5674C16.7844 21.0831 14.4993 22 12 22C6.47715 22 2 17.5228 2 12C2 9.86386 2.66979 7.88416 3.8108 6.25944L7 12H4C4 16.4183 7.58172 20 12 20C14.1502 20 16.1022 19.1517 17.5398 17.7716L18.5374 19.5674Z"></path>
          </svg>
        </button>  
      </div>`);

  }

  /**
   * Generaes the HTMl for the selected hero talent tree.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing information about the trees.
   */
  drawHeroTree(tree) {
    const heroTalentSelection = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.hero-talent-selection`);
    heroTalentSelection.style.display = 'none';
    const heroTalentCell = document.querySelector(`#midnight-skill-builder${this.idSuffix} #heroTree${this.idSuffix} div.hero-talent-cell`);
    const heroGridContainer = document.querySelector(`#midnight-skill-builder${this.idSuffix} #heroTree${this.idSuffix} div.grid-container`);
    heroTalentCell.style.display = 'flex';
    heroGridContainer.style.display = 'block';
    this.drawHeroTreeIcon(tree);
    this.drawHeroTreeNodes(tree);
    this.drawHeroTreeLvlRequired(tree);
  }

  /**
   * Sets the icon of selected hero tree.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing information about the trees.
   */
  drawHeroTreeIcon(tree) {
    const heroTalentIcon = document.querySelector(`#midnight-skill-builder${this.idSuffix} #heroTree${this.idSuffix} div.hero-talent-cell div.hero-talent-icon`);
    Array.from(heroTalentIcon.classList).forEach(cls => {
      if (cls !== 'hero-talent-icon') heroTalentIcon.classList.remove(cls);
    })
    heroTalentIcon?.classList.add(`${tree.classData.name}-${tree.heroTree.icon}`);
  }

  /**
   * Generates the HTML of the hero tree nodes.
   * 
   * @param {MidnightTalentCalculatorTree} tree Object containing information about the trees.
   */
  drawHeroTreeNodes(tree) {
    const _self = this;
    let heroTreeHTML = '';
    let heroTreeConnectionsHTML = `<svg class="tree-connections" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">`;
    Object.values(tree.heroTree.nodes).forEach(node => {
      let nodeType = _self.getNodeType(node);
      heroTreeHTML += this.drawNode(tree.heroTree, nodeType, node, 'hero');
    });

    Object.values(tree.heroTree.connections).forEach(connection => {
      heroTreeConnectionsHTML += this.drawHeroTreeNodeConnection(connection);
    });

    heroTreeConnectionsHTML += '</svg>';
    if (!this.embed) {
      let heroTreeGrid = document.getElementById(`heroTree${this.idSuffix}`).querySelector('.grid-container').querySelector('.grid');
      heroTreeGrid.insertAdjacentHTML('afterbegin', heroTreeHTML);
      Object.values(tree.heroTree.nodes).forEach(node => {
        let nodeType = _self.getNodeType(node);
  
        if (nodeType == 'active' || nodeType == 'passive') this.talentCalculator.eventListenerManager.roundSquareNodeActivation(node);
        else if (nodeType == 'choice') this.talentCalculator.eventListenerManager.choiceNodeActivation(node);
      });
      heroTreeGrid.insertAdjacentHTML('beforeend', heroTreeConnectionsHTML);
      heroTreeGrid.insertAdjacentHTML('beforebegin', this.drawSpentPointsContainer(tree.heroTree));
    } else {
      let embedHeroTreeGrid = document.querySelector(`#midnight-skill-builder${this.idSuffix} div[data-midnight-embedded-build="3"] div.grid`);
      embedHeroTreeGrid.insertAdjacentHTML('afterbegin', heroTreeHTML);
      embedHeroTreeGrid.insertAdjacentHTML('beforeend', heroTreeConnectionsHTML);
    }
  }

  /**
   * Renders the given connection as active.
   * 
   * @param {MidnightTalentCalculatorConnection} connection The connection that is to be activated.
   */
  makeConnectionActive(connection) {
    let connectionElement = document.querySelector(`#midnight-skill-builder${this.idSuffix} line.skill-connection[data-start="${connection.srcNode.id}"][data-end="${connection.dstNode.id}"]`)
    if (connectionElement) {
      connectionElement.classList.add("active");
    } // if the connection element does not exist, it means the tree has not already been drawn and the connection element will be drawn as the tree inits.
  }

  /**
   * Renders the given connection as inactive.
   * 
   * @param {MidnightTalentCalculatorConnection} connection The connection that is to be deativated.
   */
  makeConnectionInactive(connection) {
    // In this case, the connection must already exist, this method cannot be called before the tree has been renderer
    let connectionElement = document.querySelector(`#midnight-skill-builder${this.idSuffix} line.skill-connection[data-start="${connection.srcNode.id}"][data-end="${connection.dstNode.id}"]`)
    connectionElement.classList.remove("active");
  }

  /**
   * Updates the first demarcator element.
   * 
   * @param {string} treeType The type of the tree {'spec'|'class'}
   * @param {Number} distance The distance (required amount of spent points) left to the first checkpoint.
   */
  updateFirstCheckpointDistance(treeType, distance) {
    if (!this.embed) {
      document.querySelector(`#${treeType}Tree${this.idSuffix} div.points-demarcator.p1`).style.removeProperty("display");
      document.querySelector(`#${treeType}Tree${this.idSuffix} div.points-demarcator.p1>p`).innerHTML = distance;
    }
  }

  /**
   * Updates the second demarcator element.
   * 
   * @param {string} treeType The type of the tree {'spec'|'class'}
   * @param {Number} distance The distance (required amount of spent points) left to the second checkpoint.
   */
  updateSecondCheckpointDistance(treeType, distance) {
    if (!this.embed) {
      document.querySelector(`#${treeType}Tree${this.idSuffix} div.points-demarcator.p2`).style.removeProperty("display");
      document.querySelector(`#${treeType}Tree${this.idSuffix} div.points-demarcator.p2>p`).innerHTML = distance;
    }
  }

  /**
   * Hides the first demarcator element.
   * 
   * Should only be called if the destination to the first checkpoint is 0.
   * 
   * @param {string} treeType The type of the tree {'spec'|'class'}
   */
  hideFirstCheckpointDemarcator(treeType) {
    if (!this.embed) {
      document.querySelector(`#${treeType}Tree${this.idSuffix} div.points-demarcator.p1`).style.display = 'none';
    }
  }

  /**
   * Hides the second demarcator element.
   * 
   * Should only be called if the destination to the second checkpoint is 0.
   * 
   * @param {string} treeType The type of the tree {'spec'|'class'}
   */
  hideSecondCheckpointDemarcator(treeType) {
    if (!this.embed) {
      document.querySelector(`#${treeType}Tree${this.idSuffix} div.points-demarcator.p2`).style.display = 'none';
    }
  }

  /**
   * Renders the given node as maxed out.
   * 
   * @param {ChoiceNode|RoundNode|SquareNode} node The node that is to be made maxed out.
   */
  makeNodeMaxedOut(node) {
    const nodeHTML = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.skill-cell[data-node-id="${node.id}"]`);

    nodeHTML.classList.add("maxed-out");
  }

  /**
   * Renders a previously maxed out node as not maxed out.
   * 
   * @param {ChoiceNode|RoundNode|SquareNode} node The node that is to be not maxed out.
   */
  unmakeNodeMaxedOut(node) {
    const nodeHTML = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.skill-cell[data-node-id="${node.id}"]`);

    nodeHTML.classList.remove("maxed-out");
  }

  /**
   * Renders the node as inactive.
   * 
   * @param {ChoiceNode|RoundNode|SquareNode} node The node that is to be made inactive.
   */
  makeNodeInactive(node) {
    const nodeHTML = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.skill-cell[data-node-id="${node.id}"]`);

    nodeHTML.classList.remove("available-for-picking");
  }

  /**
   * Renders the node as active.
   * 
   * @param {ChoiceNode|RoundNode|SquareNode} node The node that is to be made active. 
   */
  unmakeNodeInactive(node) {
    const nodeHTML = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.skill-cell[data-node-id="${node.id}"]`);

    nodeHTML.classList.add("available-for-picking");
  }

  /**
   * Sets the position of displayed tooltip.
   * 
   * @param {MouseEvent} event The mouse event ('mousemove').
   * @param {HTMLDivElement} targetElement The moused over element.
   */
  addTooltipPositioning(event, targetElement) {
    let posX = event.clientX;
    let posY = event.clientY;
    let tooltipWidth = targetElement.clientWidth;
    let tooltipHeight = targetElement.clientHeight;
    let screenWidth = window.innerWidth;
    let screenHeight = window.innerHeight;
    let ofX = 0;
    let ofY = 0;

    if (screenWidth <= 560) {
        posX = (screenWidth - tooltipWidth) / 2;
        targetElement.style.left = `${posX + ofX}px`;
    } else {
        if ((posX + tooltipWidth + 20) >= screenWidth) {
            posX = posX - targetElement.clientWidth - 20;
            targetElement.style.left = `${posX + ofX}px`;
        } else {
            targetElement.style.left = `${(posX + 20 + ofX)}px`;
        }
    }

    if ((posY + tooltipHeight + 20) >= screenHeight) {
        posY = posY - tooltipHeight - 20;
        targetElement.style.top = `${posY + ofY}px`;
    } else {
        targetElement.style.top = `${(posY + 20 + ofY)}px`;
    }
  }

  /**
   * Updates the hash in the address bar.
   */
  updateHash() {
    if (this.hashLocked) {
      if (MIDNIGHTDEBUG) console.warn("Attempted to update hash while it's locked");
      return;
    }
    if (!this.embed) {
      window.location.hash = this.talentCalculator.toHash();
    }
  }

  updateApexTalentIcon(specTree) {
    const apexTalentIcon = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.apex-talent div.apex-talent-icon`);
    if (!apexTalentIcon) return;

    if (specTree.spentPoints < 20) {
      apexTalentIcon.style.borderColor = "var(--skill-tree-border-color-gray)";
      apexTalentIcon.style.filter = "grayscale(1)";
    } else if (specTree.apexTalent.usedPoints === specTree.apexTalent.maxPoints) {
      apexTalentIcon.style.borderColor = "var(--font-color-yellow)";
      apexTalentIcon.style.filter = "grayscale(0)";
    } else {
      apexTalentIcon.style.borderColor = "var(--font-color-green)";
      apexTalentIcon.style.filter = "grayscale(0)";
    }
  }

  updateApexTalentPointCounter(apexTalent) {
    const apexTalentPoints = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.apex-talent div.apex-talent-points`);
    if (!apexTalentPoints) return;

    apexTalentPoints.textContent = `${apexTalent.usedPoints}/${apexTalent.maxPoints}`;
  }

  // TODO: use when spell icon is available
  updateApexTalentSpell(apexTalent, talentIndex) {
    const apexTalentSpell = document.querySelector(`#midnight-skill-builder${this.idSuffix} div.apex-talent div.apex-talent-spells`);
    if (!apexTalentSpell) return;

    apexTalentSpell.children[talentIndex].textContent = apexTalent.spells[talentIndex].usedPoints;
  }
}

/**
 * Manages all event listeners.
 */
class MidnightTalentCalculatorEventListenerManager {
  talentCalculator;

  /**
   * Creates a event listener manager object.
   * 
   * @param {MidnightTalentCalculator} talentCalculator The talent calculator object.
   */
  constructor(talentCalculator, embed) {
    this.talentCalculator = talentCalculator;
    this.embed = embed;
  }

  /**
   * Adds a 'click' event listener to all elements in the class selector.
   */
  classSelectors() {
    var _self = this;
    document.querySelectorAll(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.playable-class`).forEach((element) => {
      element.addEventListener("click", function (e) {
        _self.talentCalculator.renderer.makeClassSelectedInClassSelectors(this.dataset.classId);
        _self.talentCalculator.renderer.drawSpecSelectors(this.dataset.classId);
      });
    });
  }

  /**
   * Adds a 'click' event listener to an element in the spec selector with given id.
   * 
   * @param {Number} specId The id of the specialization. Used to select a playable spec element.
   */
  specSelector(specId) {
    var _self = this;
    document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.playable-spec[data-class-id="${specId}"]`).addEventListener("click", async function(e) {
      _self.talentCalculator.renderer.makeSpecSelectedInSpecSelectors(this.dataset.classId);
      await _self.talentCalculator.tree.init(this.parentElement.dataset.associatedId, this.dataset.classId);
      _self.talentCalculator.renderer.updateHash();
      _self.talentCalculator.renderer.drawTree(_self.talentCalculator.tree);
      _self.talentCalculator.renderer.drawNodes(_self.talentCalculator.tree);
      _self.talentCalculator.renderer.drawHeroSelectors(_self.talentCalculator.tree);
    });
  }

  /**
   * Adds a 'click' event listener to all (both) elements in the hero talent selection.
   */
  heroSelector() {
    const heroTalentSelection = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.hero-talent-selection`);
    let elements = heroTalentSelection.querySelectorAll('div.hero-talent');
    let _self = this;
    for (let i = 0; i < elements.length; i++) {
      let element = elements[i];
      element.addEventListener("click", async function(e) {
        await _self.talentCalculator.tree.initHeroTree(i);
        _self.talentCalculator.renderer.updateHash();
        _self.talentCalculator.renderer.drawHeroTree(_self.talentCalculator.tree);
        _self.heroResetButton();
      });
    }
  }

  apexTalent(specTree) {
    const apexTalent = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.apex-talent`);
    if (!apexTalent) return;

    apexTalent.addEventListener('click', () => {
      const talentIndex = specTree.apexTalent.spells.findIndex(spell => spell.usedPoints < spell.maxRanks);
      if (talentIndex === -1) return;

      specTree.apexTalent.leftClick(talentIndex);
      this.talentCalculator.renderer.updateApexTalentIcon(specTree);
      // TODO: use when apex talent spell icon is available
      // this.talentCalculator.renderer.updateApexTalentSpell(specTree.apexTalent, talentIndex);
      this.talentCalculator.renderer.updateApexTalentPointCounter(specTree.apexTalent);
      this.talentCalculator.renderer.updateApexTalentTooltip(specTree);
    });

    apexTalent.addEventListener('contextmenu', (e) => {
      e.preventDefault();
      const talentIndex = specTree.apexTalent.spells.findLastIndex(spell => spell.usedPoints > 0);
      if (talentIndex === -1) return;

      specTree.apexTalent.rightClick(talentIndex);
      this.talentCalculator.renderer.updateApexTalentIcon(specTree);
      // TODO: use when apex talent spell icon is available
      // this.talentCalculator.renderer.updateApexTalentSpell(specTree.apexTalent, talentIndex);
      this.talentCalculator.renderer.updateApexTalentPointCounter(specTree.apexTalent);
      this.talentCalculator.renderer.updateApexTalentTooltip(specTree);
    });
  }

  apexTalentTooltip(specTree) {
    const apexTalent = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.apex-talent`);
    if (!apexTalent) return;

    apexTalent.addEventListener('mouseenter', (e) => {
      this.talentCalculator.renderer.updateApexTalentTooltip(specTree);
    });

    apexTalent.addEventListener("mousemove", (e) => {
      specTree.tree.talentCalculator.tooltipContainer.classList.add('visible');
      specTree.tree.talentCalculator.renderer.addTooltipPositioning(e, specTree.tree.talentCalculator.tooltipContainer);
    });

    apexTalent.addEventListener('mouseleave', (e) => {
      specTree.tree.talentCalculator.tooltipContainer.classList.remove('visible', 'mobile-visible');
      specTree.tree.talentCalculator.tooltipContainer.innerHTML = ``;
    });
  }

  /**
   * Adds a 'click' event listener to the reset button element in the hero tree container.
   * 
   * Clicking this button gets you back to the hero talent selection.
   */
  heroResetButton() {
    const heroReqLvlContainer = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} #heroTree${this.talentCalculator.renderer.idSuffix} div.req-lvl-container`);
    let _self = this;
    heroReqLvlContainer.querySelector(`#rsH${this.talentCalculator.renderer.idSuffix}`).addEventListener('click', (e) => { // Resets (hides) the hero talent tree and displays the hero tree selection.
      const heroGridContainer = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} #heroTree${this.talentCalculator.renderer.idSuffix} div.grid-container`);
      const heroTalentSelection = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.hero-talent-selection`);
      heroGridContainer.querySelector('.grid').innerHTML = '';
      heroGridContainer.querySelector('.spent-available-points')?.remove();
      heroTalentSelection.style.display = 'flex';
      document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} #heroTree${this.talentCalculator.renderer.idSuffix} div.hero-talent-cell`).style.display = 'none';
      heroGridContainer.style.display = 'none'; 
      _self.talentCalculator.tree.deleteHeroTree();
      _self.talentCalculator.renderer.updateHash();
      heroReqLvlContainer.remove();
    });
  }

  /**
   * Adds event listeners to the buttons in import talents popup window.
   * 
   * Adds a 'click' event listener to a button in the import popup window header which
   * when clicked closes the poupp window.
   * Adds a 'click' event listener to a button in the import popup window body which
   * when cliked imports the string and renders the talent calculator based on the import string.
   */
  importTalentsPopup() {
    const importPopup = document.querySelector(`#import-popup${this.talentCalculator.renderer.idSuffix}`);
    importPopup.querySelector('button.close-import-popup').addEventListener('click', (e) => {
      importPopup.querySelector('input.import-string-container').value = '';
      importPopup.style.display = 'none';
    });

    importPopup.querySelector('button.import-talent-tree').addEventListener('click', (e) => {
      this.talentCalculator.importString(importPopup.querySelector('input.import-string-container').value);
      importPopup.style.display = 'none';
    });
  }

  /**
   * Adds a 'click' event listener to the import talents button which when clicked hides the
   * export talents popup window and displays the import talents popup window.
   */
  importStringButton() {
    const importStringButton = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.export-open-container button.import-talent-tree`);
    importStringButton.addEventListener('click', (e) => {   
      document.querySelector(`#import-popup${this.talentCalculator.renderer.idSuffix}`).style.display = 'flex';
    });
  }

  /**
   * Adds a 'click' event listener to the export talents button which when clicked hides
   * the import talents popup window and displays the export talents popup window.
   */
  exportTalentsButton() {
    const exportTalentsButton = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.export-open-container button.export-talents`);
    const _self = this;
    exportTalentsButton.addEventListener('click', (e) => {
      const exportString = _self.talentCalculator.exportString();
      if (!navigator.clipboard) {
        console.log(exportString);
        _self.talentCalculator.showMessage('Clipboard API not supported! Export string logged to console.', 'info');
        return;
      }
      navigator.clipboard.writeText(exportString).then(() => {
        _self.talentCalculator.showMessage('Export string copied.', 'success');
      }).catch((err) => {
        _self.talentCalculator.showMessage('There was an error when copying the export string!', 'error');
        console.log(err);
      });
    });
  }

  /**
   * Adds event listeners to the export talents button in the embed display.
   */
  exportTalentsEmbed(embedTreeContainer) {
    const exportTalentsButton = embedTreeContainer.querySelector(`div.button-container.export-talents`);
    if (!exportTalentsButton) return;

    const _self = this;
    exportTalentsButton.addEventListener('click', (e) => {
      const exportString = _self.talentCalculator.exportString();
      if (!navigator.clipboard) {
        console.log(exportString);
        _self.talentCalculator.showMessage('Clipboard API not supported! Export string logged to console.', 'info');
        return;
      }
      navigator.clipboard.writeText(exportString).then(() => {
        _self.talentCalculator.showMessage('Export string copied.', 'success');
      }).catch((err) => {
        _self.talentCalculator.showMessage('There was an error when copying the export string!', 'error');
        console.log(err);
      });
    });
  }

  /**
   * Adds a 'click' event listener to the close button in the popup user message which when clicked
   * closes the user message popup.
   */
  userMessageCloseButton() {
    document.querySelector(`#user-message${this.talentCalculator.renderer.idSuffix} button`).addEventListener('click', (e) => {
      document.querySelector(`#user-message${this.talentCalculator.renderer.idSuffix}`).classList.remove('success', 'info', 'error', 'visible');
      document.querySelector(`#user-message${this.talentCalculator.renderer.idSuffix}`).querySelector('span').innerHTML = '';
    });
  }

  /**
   * Adds event listeners to a node element. Sets the position and content of tooltip.
   * 
   * Adds a 'click' event listener to the node element which when clicked calls the `leftClick`
   * method for that node.
   * Adds a 'contextmenu' event listener to the node element which when right clicked calls the
   * `rightClick` method for that node.
   * 
   * @param {RoundNode|SquareNode} node
   */
  roundSquareNodeActivation(node) {
    if (this.embed) {
      return;
    }
    document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`).addEventListener("click", (e) => {
      node.leftClick();
    });

    document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`).addEventListener("contextmenu", (e) => {
      e.preventDefault();
      node.rightClick();
    });

    document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`).addEventListener("mouseenter", (e) => {
      let tooltipContent = `
        <div class="content flex direction-column">
          <div class="spell">
            <div class="tooltip-icon-and-name flex">
              <div class="sprite ${node.subtree.classData.name}_${node.subtree.specData.name}-${node.spells[0].icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(node.subtree.tree.classData.name, node.subtree.tree.specData.name)}&quot;)"></div>
              <span class="tooltip-name">${node.spells[0].name}</span>
            </div>
            <div class="midnight-tooltip-description">${node.spells[0].rankDescriptions[node.currentPoints - 1] ?? node.spells[0].description}</div>
          </div>
        </div>
      `;
      node.subtree.tree.talentCalculator.tooltipContainer.innerHTML = tooltipContent;
    });

    document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`).addEventListener("mousemove", (e) => {
      node.subtree.tree.talentCalculator.tooltipContainer.classList.add('visible');
      node.subtree.tree.talentCalculator.renderer.addTooltipPositioning(e, node.subtree.tree.talentCalculator.tooltipContainer);
    });    

    document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`).addEventListener('mouseleave', (e) => {
      node.subtree.tree.talentCalculator.tooltipContainer.classList.remove('visible', 'mobile-visible');
      node.subtree.tree.talentCalculator.tooltipContainer.innerHTML = ``;
    });
  }

  /**
   * Adds even listeners to a choice node element. Sets the position and content of tooltip.
   * 
   * Adds a 'click' event listener to the node element which when clicked calls the `leftClick`
   * method for that choice node.
   * Adds a 'contextmenu' event listener to the node element which when clicked calls the `rightClick`
   * method for that choice node.
   * 
   * @param {ChoiceNode} node
   */
  choiceNodeActivation(node) {
    const _self = this;
    const nodeHTML = document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`);    
    
    if (this.embed) {
      return;
    }
    const choicePopup =  nodeHTML.querySelector(`div.choice-popup`);

    nodeHTML.addEventListener('click', (e) => {
      const nodeHTMLMaxedOut = nodeHTML.classList.contains('maxed-out');

      if (nodeHTMLMaxedOut) {    
        const otherSkill = nodeHTML.querySelector('div.skill-icon div:not(.selected)');
        nodeHTML.querySelector('div.selected').classList.remove('selected');
        otherSkill.classList.add('selected');

        node.toggleChoice();
        _self.talentCalculator.renderer.updateHash();      
      }
    });

    nodeHTML.addEventListener('mouseenter', (e) => {
      if (!nodeHTML.classList.contains('maxed-out') && nodeHTML.classList.contains('available-for-picking')) choicePopup.style.display = 'block';
    });

    Array.from(choicePopup.children).forEach(option => {
      option.addEventListener("click", (e) => {
        e.stopPropagation();
        node.leftClick(parseInt(option.dataset.choiceId));
        choicePopup.style.display = 'none';
      });

      option.addEventListener('mouseenter', (e) => {
        let tooltipContent = `
            <div class="content flex direction-column">
              <div class="spell">
                <div class="tooltip-icon-and-name flex">
                  <div class="sprite ${node.subtree.classData.name}_${node.subtree.specData.name}-${node.spells[option.dataset.tooltipSpellIndex].icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(node.subtree.tree.classData.name, node.subtree.tree.specData.name)}&quot;)"></div>
                  <span class="tooltip-name">${node.spells[option.dataset.tooltipSpellIndex].name}</span>
                </div>
                <div class="midnight-tooltip-description">${node.spells[option.dataset.tooltipSpellIndex].description}</div>
              </div>
            </div>            
        `;
        node.subtree.tree.talentCalculator.tooltipContainer.innerHTML = tooltipContent;
      });
    });

    nodeHTML.addEventListener("contextmenu", (e) => {
      e.preventDefault();
      if (!nodeHTML.classList.contains('maxed-out')) return;

      node.subtree.tree.talentCalculator.renderer.deselectChoiceNodeOption(node);
      node.subtree.tree.talentCalculator.renderer.updateChoiceNodeTooltip(node);
      node.rightClick();
      choicePopup.style.display = 'block';
    });

    nodeHTML.addEventListener("mouseenter", (e) => {
      const nodeContainer = document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`);      
      let tooltipContent = '<div class="content flex direction-column">';
      let associatedSpell;
      for (const spell of Array.from(nodeContainer.querySelector('div.clip-container div.skill-icon').children)) {
        if (spell.classList.contains('selected')) {
          associatedSpell = node.spells.find((s) => s.spellId == spell.dataset.spellId);
          tooltipContent = `
            <div class="content flex direction-column">
              <div class="spell">
                <div class="tooltip-icon-and-name flex">
                  <div class="sprite ${node.subtree.classData.name}_${node.subtree.specData.name}-${associatedSpell.icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(node.subtree.tree.classData.name, node.subtree.tree.specData.name)}&quot;)"></div>
                  <span class="tooltip-name">${associatedSpell.name}</span>
                </div>
                <div class="midnight-tooltip-description">${associatedSpell.rankDescriptions[node.currentPoints - 1] ?? associatedSpell.description}</div>
              </div>
          `;
          break;
        }
        associatedSpell = node.spells.find((s) => s.spellId == spell.dataset.spellId);
        tooltipContent += `
              <div class="spell">
                <div class="tooltip-icon-and-name flex">
                  <div class="sprite ${node.subtree.classData.name}_${node.subtree.specData.name}-${associatedSpell.icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(node.subtree.tree.classData.name, node.subtree.tree.specData.name)}&quot;)"></div>
                  <span class="tooltip-name">${associatedSpell.name}</span>
                </div>
                <div class="midnight-tooltip-description">${associatedSpell.rankDescriptions[node.currentPoints - 1] ?? associatedSpell.description}</div>
              </div>
        `;
      }

      tooltipContent += '</div>';
      node.subtree.tree.talentCalculator.tooltipContainer.innerHTML = tooltipContent;
    });

    nodeHTML.addEventListener("mousemove", (e) => {
      node.subtree.tree.talentCalculator.tooltipContainer.classList.add('visible');
      node.subtree.tree.talentCalculator.renderer.addTooltipPositioning(e, node.subtree.tree.talentCalculator.tooltipContainer);
    });    

    nodeHTML.addEventListener('mouseleave', (e) => {
      node.subtree.tree.talentCalculator.tooltipContainer.classList.remove('visible', 'mobile-visible');
      node.subtree.tree.talentCalculator.tooltipContainer.innerHTML = ``;
      choicePopup.style.display = 'none';
    });

  }

  /**
   * Adds mouse events to nodes in the talent by level display. Handles tooltip container.
   * 
   * @param {MidnightTalentCalculatorNode | MidnightTalentCalculatorApexTalent} node
   */
  nodeTalentByLevel(node, choice=null) {
    const nodeHTML = document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelectorAll(`li.list-spell[data-node-id="${node.id}"] div.name-of-spell`);
    for (const listNodeHTML of nodeHTML) {
      if (node instanceof MidnightTalentCalculatorApexTalent) {
        listNodeHTML.addEventListener('mouseenter', (e) => {
          document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.apex-talent-container div.apex-talent div.apex-talent-icon`).style.boxShadow = '0 0 16px var(--font-color-yellow)';
        });
        
        listNodeHTML.addEventListener('mouseleave', (e) => {
          document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.apex-talent-container div.apex-talent div.apex-talent-icon`).style.boxShadow = '';
        });
      } else {
        listNodeHTML.addEventListener('mouseenter', (e) => {
          document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${node.id}"] div.skill`).style.boxShadow = '0 0 16px var(--font-color-yellow)';
        });
        
        listNodeHTML.addEventListener('mouseleave', (e) => {
          document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.skill-cell[data-node-id="${node.id}"] div.skill`).style.boxShadow = '';
        });
      }
    }
  }

  /**
   * Adds mouse events to round or square nodes from sub-trees in embed display. Handles the tooltip container.
   * 
   * @param {MidnightTalentCalculatorNode} node
   */
  roundSquareNodeEmbed(node) {
    document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`).addEventListener("mouseenter", (e) => {
      let tooltipContent = `
        <div class="content flex direction-column">
          <div class="spell">
            <div class="tooltip-icon-and-name flex">
              <div class="sprite ${node.subtree.classData.name}_${node.subtree.specData.name}-${node.spells[0].icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(node.subtree.tree.classData.name, node.subtree.tree.specData.name)}&quot;)"></div>
              <span class="tooltip-name">${node.spells[0].name}</span>
            </div>
            <div class="midnight-tooltip-description">${node.spells[0].rankDescriptions[node.currentPoints - 1] ?? node.spells[0].description}</div>
          </div>
        </div>
      `;
      document.getElementById('midnight-skill-builder-tooltip').innerHTML = tooltipContent;
    });

    document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`).addEventListener("mousemove", (e) => {
      document.getElementById('midnight-skill-builder-tooltip').classList.add('visible');
      node.subtree.tree.talentCalculator.renderer.addTooltipPositioning(e, document.getElementById('midnight-skill-builder-tooltip'));
    });    

    document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`).addEventListener('mouseleave', (e) => {
      document.getElementById('midnight-skill-builder-tooltip').classList.remove('visible', 'mobile-visible');
      document.getElementById('midnight-skill-builder-tooltip').innerHTML = ``;
    });
  }

  /**
   * Adds mouse events to choice nodes from sub-trees in embed display. Handles the tooltip container.
   * 
   * @param {MidnightTalentCalculatorNode} node
   */  
  choiceNodeEmbed(node) {
    const nodeHTML = document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`);
    nodeHTML.addEventListener("mouseenter", (e) => {
      const nodeContainer = document.getElementById(`midnight-skill-builder${this.talentCalculator.renderer.idSuffix}`).querySelector(`div.skill-cell[data-node-id="${node.id}"]`);      
      let tooltipContent = '<div class="content flex direction-column">';
      let associatedSpell;
      for (const spell of Array.from(nodeContainer.querySelector('div.clip-container div.skill-icon').children)) {
        if (spell.classList.contains('selected')) {
          associatedSpell = node.spells.find((s) => s.spellId == spell.dataset.spellId);
          tooltipContent = `
            <div class="content flex direction-column">
              <div class="spell">
                <div class="tooltip-icon-and-name flex">
                  <div class="sprite ${node.subtree.classData.name}_${node.subtree.specData.name}-${associatedSpell.icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(node.subtree.tree.classData.name, node.subtree.tree.specData.name)}&quot;)"></div>
                  <span class="tooltip-name">${associatedSpell.name}</span>
                </div>
                <div class="midnight-tooltip-description">${associatedSpell.rankDescriptions[node.currentPoints - 1] ?? associatedSpell.description}</div>
              </div>
          `;
          break;
        }
        associatedSpell = node.spells.find((s) => s.spellId == spell.dataset.spellId);
        tooltipContent += `
              <div class="spell">
                <div class="tooltip-icon-and-name flex">
                  <div class="sprite ${node.subtree.classData.name}_${node.subtree.specData.name}-${associatedSpell.icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(node.subtree.tree.classData.name, node.subtree.tree.specData.name)}&quot;)"></div>
                  <span class="tooltip-name">${associatedSpell.name}</span>
                </div>
                <div class="midnight-tooltip-description">${associatedSpell.rankDescriptions[node.currentPoints - 1] ?? associatedSpell.description}</div>
              </div>
        `;
      }

      tooltipContent += '</div>';
      document.getElementById('midnight-skill-builder-tooltip').innerHTML = tooltipContent;
    });

    nodeHTML.addEventListener("mousemove", (e) => {
      document.getElementById('midnight-skill-builder-tooltip').classList.add('visible');
      node.subtree.tree.talentCalculator.renderer.addTooltipPositioning(e, document.getElementById('midnight-skill-builder-tooltip'));
    });    

    nodeHTML.addEventListener('mouseleave', (e) => {
      document.getElementById('midnight-skill-builder-tooltip').classList.remove('visible', 'mobile-visible');
      document.getElementById('midnight-skill-builder-tooltip').innerHTML = ``;
    });
  }

  /**
   * Toggles between talents by points embed display and reqular embed display.
   * 
   * @param {HTMLDivElement} embedTree The tree HTML element.
   */
  toggleTalentsByPoints(embedTree) {
    const toggleTalentsByPointsButton = embedTree.querySelector('div.button-container.toggle');
    if (!toggleTalentsByPointsButton) return;

    toggleTalentsByPointsButton.addEventListener('click', (e) => {
      if (toggleTalentsByPointsButton.classList.contains('active')) {
        toggleTalentsByPointsButton.classList.remove('active');
        toggleTalentsByPointsButton.querySelector('div.toggle-box').textContent = 'OFF';
        embedTree.querySelector('div.embed-content-wrapper').classList.remove('shown-points-by-level');
        embedTree.querySelector('div.embed-content-wrapper').classList.add('hidden-points-by-level');
      } else {
        toggleTalentsByPointsButton.classList.add('active');
        embedTree.querySelector('div.embed-content-wrapper').classList.remove('hidden-points-by-level');
        embedTree.querySelector('div.embed-content-wrapper').classList.add('shown-points-by-level');        
        toggleTalentsByPointsButton.querySelector('div.toggle-box').textContent = 'ON';
      }
    });
  }

  /**
   * Adds 'click' event listeners to the class and spec tree reset buttons which when clicked
   * call the `reset` method for that tree.
   */
  resetButtons() {
    var _self = this;
    document.getElementById(`rsC${this.talentCalculator.renderer.idSuffix}`).addEventListener('click', function(e) {
      _self.talentCalculator.tree.classTree.reset();
      _self.talentCalculator.renderer.updateHash();
    });
    document.getElementById(`rsS${this.talentCalculator.renderer.idSuffix}`).addEventListener('click', function(e) {
      _self.talentCalculator.tree.specTree.reset();
      _self.talentCalculator.renderer.updateHash();
    });
  }

  /**
   * Adds a 'click' event listener to closing button in the pvp talents popup which when clicked
   * closes the pvp talents popup window.
   */
  pvpTalentsPopupHeader() {
    const pvpTalentsPopup = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div#pvp-talents-container${this.talentCalculator.renderer.idSuffix} div.pvp-talents-pop-up`);
    pvpTalentsPopup.querySelector('button.close-pvp-talents-list').addEventListener('click', (e) => {
      pvpTalentsPopup.classList.remove('active');
    });
  }

  /**
   * Adds event listener to pvp talent slots, individual entries in pvp talent popup.
   * Adds event listener to 'remove-current-talent' button. 
   */
  pvpTalentsSelector() {
    const pvpTalentSelection = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.pvp-talents-section`);
    const pvpTalentsPopup = document.querySelector(`#midnight-skill-builder${this.talentCalculator.renderer.idSuffix} div.pvp-talents-pop-up`)
    const pvpTalentsPopupTalentList = pvpTalentsPopup.querySelector('.pvp-talents-list');
    let elements = pvpTalentSelection.querySelectorAll('div.pvp-slot-container');
    let _self = this;

    for (const element of elements) {
      element.addEventListener('click', (e) => {
        pvpTalentsPopup.classList.add('active');

        // Prevents the duplication of event listeners
        // TODO
        Array.from(pvpTalentsPopupTalentList.children).forEach(pvpTalent => {
          pvpTalent.replaceWith(pvpTalent.cloneNode(true));
        });

        Array.from(pvpTalentsPopupTalentList.children).forEach(pvpTalent => {
          pvpTalent.addEventListener('click', (e) => {
            let occupiedSlotBySelectedTalentIndex = null;            
            Object.entries(_self.talentCalculator.tree.chosenPvPTalents).forEach(([key, value]) => {
              if (value?.id == pvpTalent.dataset.pvpTalentId) occupiedSlotBySelectedTalentIndex = key;
            });
            if (occupiedSlotBySelectedTalentIndex == element.dataset.index) { pvpTalentsPopup.classList.remove('active'); return;}

            _self.talentCalculator.tree.choosePvPTalent(pvpTalent.dataset.pvpTalentId, element.dataset.index);
            _self.talentCalculator.renderer.refreshPvpTalent(element.dataset.index);
            element.classList.add('active');
            pvpTalentsPopup.classList.remove('active');
            if (occupiedSlotBySelectedTalentIndex !== null) {
              _self.talentCalculator.tree.chosenPvPTalents[occupiedSlotBySelectedTalentIndex] = null;
              _self.talentCalculator.renderer.refreshPvpTalent(occupiedSlotBySelectedTalentIndex);
              const occupiedSlotBySelectedTalent = pvpTalentSelection.querySelector(`div.pvp-slot-container[data-index="${occupiedSlotBySelectedTalentIndex}"]`);
              occupiedSlotBySelectedTalent.classList.remove('active');
            }                        
          });
        });
      });
      
      pvpTalentsPopup.querySelector('div.pvp-talents-list-header button.remove-current-talent').addEventListener('click', (e) => {
        _self.talentCalculator.tree.chosenPvPTalents[element.dataset.index] = null;
        _self.talentCalculator.renderer.refreshPvpTalent(element.dataset.index);
        element.classList.remove('active');
        pvpTalentsPopup.classList.remove('active');
      });

      element.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        _self.talentCalculator.tree.chosenPvPTalents[element.dataset.index] = null;
        _self.talentCalculator.renderer.refreshPvpTalent(element.dataset.index);
        element.classList.remove('active');
      });

      element.addEventListener('mouseenter', (e) => {
        if (!element.classList.contains('active')) return;

        const chosenPvpTalent = _self.talentCalculator.tree.chosenPvPTalents[element.dataset.index];
        let tooltipContent = `
          <div class="content flex direction-column">
            <div class="spell">
              <div class="tooltip-icon-and-name flex">
              <div class="sprite ${_self.talentCalculator.tree.classData.name}_${_self.talentCalculator.tree.specData.name}-${chosenPvpTalent.icon}" style="background-image: url(&quot;${MidnightTalentCalculatorSpriteHelper.specSprite(_self.talentCalculator.tree.classData.name, _self.talentCalculator.tree.specData.name)}&quot;)"></div>
                <span class="tooltip-name">${chosenPvpTalent.name}</span>
              </div>
              <div class="midnight-tooltip-description">${chosenPvpTalent.description}</div>
            </div>
          </div>
        `;
        _self.talentCalculator.tooltipContainer.innerHTML = tooltipContent;
      });

      element.addEventListener("mousemove", (e) => {
        if (!element.classList.contains('active')) return;

        _self.talentCalculator.tooltipContainer.classList.add('visible');
        _self.talentCalculator.renderer.addTooltipPositioning(e, _self.talentCalculator.tooltipContainer);
      });
      
      element.addEventListener('mouseleave', (e) => {
        _self.talentCalculator.tooltipContainer.classList.remove('visible');
        _self.talentCalculator.tooltipContainer.innerHTML = ``;
      });
    }
  }

  /**
   * Adds a 'click' event listener to the svg element in the header of an embed tree.
   * 
   * @param {HTMLDivElement} embedContainer Container of the embed subtree.
   */
  embedHeader(embedContainer) {
    if (!embedContainer.querySelector('div.embed-header')) return;

    const talentByLevelToggle = embedContainer.querySelector('div.embed-share-build-toggle');
    if (!embedContainer.querySelector('div.point-allocation-breakdown').classList?.contains('active') && talentByLevelToggle) talentByLevelToggle.style.display = 'none';

    embedContainer.querySelector('div.embed-header svg').addEventListener('click', (e) => {
      const embedTree = embedContainer.querySelector('div.point-allocation-breakdown');
      if (embedTree.classList.contains('active')) {
        embedTree.classList.remove('active');
        embedContainer.querySelector('div.embed-header svg path').setAttribute('d', 'M11.9999 13.1714L16.9497 8.22168L18.3639 9.63589L11.9999 15.9999L5.63599 9.63589L7.0502 8.22168L11.9999 13.1714Z');
        if (talentByLevelToggle) embedContainer.querySelector('div.embed-share-build-toggle').style.display = 'none';
      } else {
        embedTree.classList.add('active');
        embedContainer.querySelector('div.embed-header svg path').setAttribute('d', 'M11.9999 10.8284L7.0502 15.7782L5.63599 14.364L11.9999 8L18.3639 14.364L16.9497 15.7782L11.9999 10.8284Z');
        if (talentByLevelToggle) embedContainer.querySelector('div.embed-share-build-toggle').style.display = 'flex';
      }
    });

    embedContainer.querySelector('div.embed-header div.embed-name').addEventListener('click', (e) => {
      const embedTree = embedContainer.querySelector('div.point-allocation-breakdown');
      if (embedTree.classList.contains('active')) {
        embedTree.classList.remove('active');
        embedContainer.querySelector('div.embed-header svg path').setAttribute('d', 'M11.9999 13.1714L16.9497 8.22168L18.3639 9.63589L11.9999 15.9999L5.63599 9.63589L7.0502 8.22168L11.9999 13.1714Z');
        if (talentByLevelToggle) embedContainer.querySelector('div.embed-share-build-toggle').style.display = 'none';
      } else {
        embedTree.classList.add('active');
        embedContainer.querySelector('div.embed-header svg path').setAttribute('d', 'M11.9999 10.8284L7.0502 15.7782L5.63599 14.364L11.9999 8L18.3639 14.364L16.9497 15.7782L11.9999 10.8284Z');
        if (talentByLevelToggle) embedContainer.querySelector('div.embed-share-build-toggle').style.display = 'flex';
      }
    });
  }
}

class MidnightTalentCalculator {
  targetElement;
  tree;
  renderer;
  tooltipContainer;

  /**
   * Generates the tooltip container HTML.
   */
  generateTooltipContainer() {
    let tooltipElement = document.getElementById('midnight-skill-builder-tooltip');
    if (tooltipElement) return;

    tooltipElement = document.createElement('div');
    tooltipElement.setAttribute('id', 'midnight-skill-builder-tooltip');
    tooltipElement.setAttribute('class', 'flex direction-column');

    document.body.appendChild(tooltipElement);
    this.tooltipContainer = tooltipElement;
  }

  /**
   * Renders the user message popup based on given parameters.
   * 
   * @param {string} message The message that is to be displayed in the popup.
   * @param {string} type The type of the message which determines how the popup looks.
   */
  showMessage(message, type) {
    const userMessageContainer = document.querySelector(`#user-message${this.renderer.idSuffix}`);
    userMessageContainer.classList.remove('success', 'info', 'error');
    userMessageContainer.querySelector('span').innerHTML = message;

    switch (type) {
        case 'success':
            userMessageContainer.classList.add('success', 'visible');
            break;
        case 'info':
            userMessageContainer.classList.add('info', 'visible');
            break;
        case 'error':
            userMessageContainer.classList.add('error', 'visible');
            break;
    }

    this.hideMessage();
  }

  /**
   * Hides the user message popup after a timeout.
   */
  hideMessage() {
    const userMessageContainer = document.querySelector(`#user-message${this.renderer.idSuffix}`);
    setTimeout(() => {
      userMessageContainer.classList.remove('success', 'info', 'error', 'visible');
      userMessageContainer.querySelector('span').innerHTML = '';
    }, 5000);
  }

  /**
   * Creates a talent calculator object.
   * 
   * @param {string} targetElementId
   */
  constructor(targetElementId, hash, idSuffix, options) {
    this.targetElement = document.getElementById(targetElementId);
    // Instantiate tree container with hash and draw it. If hash is empty, it will display the selectors
    if (hash) {
      this.hash = hash;
    } else {
      this.hash = window.location.hash;
    }
    this.embed = options.embed;
    this.heroOnly = options.heroOnly;
    this.collapseEmbed = options.collapseEmbed;
    this.displayLevels = options.displayLevels;
    this.ptr = options.ptr;
    if (this.ptr) {
      MidnightTalentCalculatorJSON.jsonPath = "/proxy/icy-veins/json/midnight-talent-calculator-ptr";
      MidnightTalentCalculatorSpriteHelper.gameVersion = 'ptr';
    }
    this.idSuffix = idSuffix;
    this.tree = new MidnightTalentCalculatorTree(this, this.embed);
    this.renderer = new MidnightTalentCalculatorRenderer(this.targetElement, this, idSuffix, this.embed, this.displayLevels, this.heroOnly, this.collapseEmbed);
  }

  async run() {
    this.eventListenerManager = new MidnightTalentCalculatorEventListenerManager(this, this.embed);
    if (this.embed) {
      this.renderer.drawTreeContainerEmbed();
    } else {
      await this.renderer.drawClassSelectors();
    }
    this.processHash(this.hash);
    this.generateTooltipContainer();
  }

  subtreeFromHash(reader, nodes, treeType) {
    while (reader.arrayIndex < reader.array.length) {
      let nodeIndex = reader.read(6);
      if (nodeIndex == -1) {
        break;
      }
      let nodeId = nodes[nodeIndex];
      if (treeType === 'apex') {
        const talentIndex = this.tree.specTree.apexTalent.spells.findIndex(spell => spell.usedPoints < spell.maxRanks);
        this.tree.specTree.apexTalent.leftClick(talentIndex);
        this.renderer.updateApexTalentIcon(this.tree.specTree);
        // TODO: use when apex talent spell icon is available
        // this.renderer.updateApexTalentSpell(specTree.apexTalent, talentIndex);
        this.renderer.updateApexTalentPointCounter(this.tree.specTree.apexTalent);
        if (this.embed) {
          this.renderer.drawNodeTalentByLevel(this.tree, this.tree.specTree.apexTalent, 'passive', this.tree.specTree.requiredLevel, 'spec');
        }
        continue;
      }
      let node = this.tree.getNodeFromId(nodeId);
      if (node instanceof MidnightTalentCalculatorNodeChoice) {
        let choice = reader.read(1);
        node.leftClick(choice, { force: true });
        if (this.embed) {
          if (treeType === 'class') this.renderer.drawNodeTalentByLevel(this.tree, node, this.renderer.getNodeType(node), this.tree.classTree.requiredLevel, treeType, choice);
          else if (treeType === 'spec') this.renderer.drawNodeTalentByLevel(this.tree, node, this.renderer.getNodeType(node), this.tree.specTree.requiredLevel, treeType, choice);
          else if (treeType === 'hero') this.renderer.drawNodeTalentByLevel(this.tree, node, this.renderer.getNodeType(node), this.tree.heroTree.requiredLevel, treeType, choice);
        }
      } else {
        node.leftClick({ force: true });
        if (this.embed) {
          if (treeType === 'class') this.renderer.drawNodeTalentByLevel(this.tree, node, this.renderer.getNodeType(node), this.tree.classTree.requiredLevel, treeType);
          else if (treeType === 'spec') this.renderer.drawNodeTalentByLevel(this.tree, node, this.renderer.getNodeType(node), this.tree.specTree.requiredLevel, treeType);
          else if (treeType === 'hero') this.renderer.drawNodeTalentByLevel(this.tree, node, this.renderer.getNodeType(node), this.tree.heroTree.requiredLevel, treeType);        
        }
      }
    }
    if (this.embed) this.renderer.setEmbedReqLvl(this.tree, treeType);
  }

  async processHash(hash) {
    if (hash == '') {
      return false;
    }

    this.renderer.hashLocked = true;

    let hashParts = hash.replace('#', '').split('-');
    const hashPartsLength = hashParts.length;
    let specIdString = hashParts[0];
    let classString = hashParts[1];
    let specString = hashParts[2];
    let apexTalentString = hashPartsLength === 6 ? hashParts[3] : "";
    let heroString = hashParts[hashPartsLength === 6 ? 4 : 3];
    let pvpString = hashParts[hashPartsLength === 6 ? 5 : 4];

    let reader = new BinaryArrayReader(specIdString, hashBase64Table);
    let specId = reader.read(12);
    if (await this.initTreeFromSpecId(specId) == -1) {
      return false;};

    if (classString != "" && !this.heroOnly) {
      if (MIDNIGHTDEBUG) console.log("importing class tree: " + classString);
      if (!this.embed) {
        this.subtreeFromHash(new BinaryArrayReader(classString, hashBase64Table), this.tree.classNodeIds, 'class');
      } else { 
        this.renderer.drawClassTreeEmbed(this.tree);
        this.subtreeFromHash(new BinaryArrayReader(classString, hashBase64Table), this.tree.classNodeIds, 'class');
      }
    }

    if (specString != "" && !this.heroOnly) {
      if (MIDNIGHTDEBUG) console.log("importing spec tree: " + specString);
      if (!this.embed) {
        this.subtreeFromHash(new BinaryArrayReader(specString, hashBase64Table), this.tree.specNodeIds, 'spec');
      } else {
        this.renderer.drawSpecTreeEmbed(this.tree);
        this.subtreeFromHash(new BinaryArrayReader(specString, hashBase64Table), this.tree.specNodeIds, 'spec');
      }
    }
    
    if (apexTalentString !== "" && !this.heroOnly) {
      this.subtreeFromHash(new BinaryArrayReader(apexTalentString, hashBase64Table), [this.tree.specTree.apexTalent.id], 'apex');
    }

    if (heroString != "") {
      if (MIDNIGHTDEBUG) console.log("importing hero tree: " + heroString);
      const heroReader = new BinaryArrayReader(heroString, hashBase64Table);
      if (!this.embed) {
        let choice = heroReader.read(1);
        await this.tree.initHeroTree(choice);
        this.renderer.drawHeroTree(this.tree);
        this.eventListenerManager.heroResetButton();
        this.subtreeFromHash(heroReader, this.tree.heroNodeIds, 'hero');
      } else {
        let choice = heroReader.read(1);
        await this.tree.initHeroTree(choice);
        this.renderer.drawHeroTreeEmbed(this.tree);
        this.subtreeFromHash(heroReader, this.tree.heroNodeIds, 'hero');
      }
    }

    if (pvpString != "") {
      reader = new BinaryArrayReader(pvpString, hashBase64Table);
      [0, 1, 2].forEach(index => {
        let pvpTalentIndex = reader.read(6) - 1;
        if (pvpTalentIndex != -1) {
          let pvpTalentId = this.tree.pvpTalentIds[pvpTalentIndex];
          this.tree.choosePvPTalent(pvpTalentId, index);
          this.renderer.refreshPvpTalent(index);
        }
      });
    }
    this.renderer.hashLocked = false;

    this.renderer.updateHash();
    

    return true;
  }

  /**
   * Converts the subtree to a hash.
   * 
   * @param {ClassTree|SpecTree|HeroTree} subtree The tree object to be converted to a hash.
   * @param {Array} nodeIds An array of node ids.
   * @returns {string} The hash for the given subtree.
   */
  subtreeToHash(subtree, nodeIds) {
    let writer = new BinaryArrayWriter(hashBase64Table);

    if (subtree instanceof MidnightTalentCalculatorHeroTree) {
      writer.write(this.tree.heroTreeChosen, 1);
    }
    if (subtree.history.actions) {
      subtree.history.actions.forEach(action => {
        const nodeIndex = nodeIds.indexOf(action.nodeId);
        if (nodeIndex === -1) return;

        writer.write(nodeIndex, 6);
        if (action.choice != null) {
          writer.write(action.choice, 1);
        }
      });
    }
    return writer.toExportString();
  }
/**
 * Converts the entire tree to a hash.
 * 
 * @returns {string} The complete hash with all subtree hashes.
 */
  toHash() {
    let hash = "";
    let specWriter = new BinaryArrayWriter(hashBase64Table);
    // Encoding spec on 12 bits, so 2 base64 characters
    specWriter.write(this.tree.specData.id, 12);
    hash += specWriter.toExportString();

    // Class Tree
    hash += "-";
    hash += this.subtreeToHash(this.tree.classTree, this.tree.classNodeIds);

    // Spec Tree
    hash += "-";
    hash += this.subtreeToHash(this.tree.specTree, this.tree.specNodeIds);

    // Apex Talent
    hash += "-";
    hash += this.subtreeToHash(this.tree.specTree, [this.tree.specTree.apexTalent.id]);

    // Hero Tree
    hash += "-";
    if (this.tree.heroTreeChosen != undefined) {
      console.log(this.tree.heroNodeIds);
      hash += this.subtreeToHash(this.tree.heroTree, this.tree.heroNodeIds);
    }
    
    // PvP Talents
    hash += "-";
    let pvpWriter = new BinaryArrayWriter(hashBase64Table);
    [0, 1, 2].forEach(index => {
      if (this.tree.chosenPvPTalents[index]) {
        pvpWriter.write(this.tree.pvpTalentIds.indexOf(this.tree.chosenPvPTalents[index].id) + 1, 6);
      } else {
        pvpWriter.write(0, 6);
      }
    });
    let pvpString = pvpWriter.toExportString();
    if (pvpString != "AAA")
      hash += pvpString;
    return hash;
  }

  /**
   * Initializes tree from specialization id.
   * 
   * @param {Number} specId The specialization id.
   * @returns -1 if there are no data for specialization with given ID, 1 otherwise.
   */
  async initTreeFromSpecId(specId) {
    let json = await MidnightTalentCalculatorJSON.get("classes_basic_info");
    let classData;
    let specData;
    json.forEach(classData_ => {
      classData_.specializations.forEach(specData_ => {
        if (specData_.id == specId) {
          classData = classData_;
          specData = specData_;
        }
      });
    });
    if (!specData)
      return -1;
    if (!this.embed) {
      this.renderer.makeClassSelectedInClassSelectors(classData.id);
      await this.renderer.drawSpecSelectors(classData.id);
      this.renderer.makeSpecSelectedInSpecSelectors(specData.id);
      await this.tree.init(classData.id, specData.id);
      this.renderer.drawTree(this.tree);
      this.renderer.drawNodes(this.tree);
      this.renderer.drawHeroSelectors(this.tree);
    } else {
      await this.tree.init(classData.id, specData.id);
      this.renderer.drawClassTreeEmbed(this.tree);
      this.renderer.drawSpecTreeEmbed(this.tree);
      this.renderer.drawNodes(this.tree);
    }
    return 1;
  }

  /**
   * Renders the trees from an import string.
   * 
   * @param {string} string The import string from which the trees will be rendered.
   */
  async importString(string) {
    let reader = new BinaryArrayReader(string, base64Table);
    let serializationVersion = reader.read(8);
    let specId = reader.read(16);
    await this.initTreeFromSpecId(specId);

    let unused = reader.read(128);

    let actions = {};

    let nodeIds = this.tree.allNodeIds;
    nodeIds.forEach(nodeId => {
      let isSelectedNode = reader.read(1);
      if (isSelectedNode == 1) {
        // Node is selected
        let isPurchasedNode = reader.read(1)
        if (isPurchasedNode == 1) {
          // Points were invested in the node, i.e. it is not permanently maxed out
          let isPartiallyRankedNode = reader.read(1);
          if (isPartiallyRankedNode == 1) {
            let pointsInvested = reader.read(6);
            actions[nodeId] = {'pointsInvested': pointsInvested};
          } else {
            actions[nodeId] = {'pointsInvested': -1};
          }
          let isChoiceNode = reader.read(1);
          if (isChoiceNode == 1) {
            let entryChosen = reader.read(2);
            actions[nodeId]['entryChosen'] = entryChosen;
          }
        }
      }
    });
    if (actions[this.tree.heroMetaNodeId]) {
      await this.tree.initHeroTree(actions[this.tree.heroMetaNodeId].entryChosen);
      // BlastR patch: Icy Veins's importString forgot to branch on this.embed
      // here. Without this, drawHeroTree() crashes in embed mode because the
      // full-page-only `.hero-talent-selection` element doesn't exist.
      if (this.embed) {
        this.renderer.drawHeroTreeEmbed(this.tree);
      } else {
        this.renderer.drawHeroTree(this.tree);
        this.eventListenerManager.heroResetButton();
      }
    }
    let remainingNodeIds = new Set(Object.keys(actions).map(key => parseInt(key)));
    remainingNodeIds.delete(this.tree.heroMetaNodeId);
    remainingNodeIds.delete(this.tree.apexTalentId);
    while (remainingNodeIds.size != 0) {
      Array.from(remainingNodeIds).forEach(nodeId => {
        let node = this.tree.getNodeFromId(nodeId);
        if (!node) {
          console.error(`Node with id ${nodeId} not found in tree.`);
          remainingNodeIds.delete(nodeId);
          return;
        }
        if (node.state == 'active') {
          remainingNodeIds.delete(nodeId);
          if (actions[nodeId].entryChosen != null) {
            node.leftClick(actions[nodeId].entryChosen);
          } else {
            let toInvest = node.maxPoints;
            if (actions[nodeId].pointsInvested != -1) {
              toInvest = actions[nodeId].pointsInvested;
            }
            const currentPoints = node.currentPoints;
            for (let i = 0; i < toInvest - currentPoints; i++) {
              node.leftClick();
            }
          }
        }
      });
      if (remainingNodeIds.size && !Array.from(remainingNodeIds).some(nodeId => this.tree.getNodeFromId(nodeId)?.state == 'active')) {
        console.error("Some nodes could not be processed due to unmet dependencies.", Array.from(remainingNodeIds).map(nodeId => this.tree.getNodeFromId(nodeId)?.state));
        break;
      }
    }
    if (actions[this.tree.apexTalentId]) {
      let toInvest = actions[this.tree.apexTalentId].pointsInvested;
      for (let i = 0; i < (toInvest === -1 ? this.tree.specTree.apexTalent.maxPoints : toInvest); i++) {
        const talentIndex = this.tree.specTree.apexTalent.spells.findIndex(spell => spell.usedPoints < spell.maxRanks);
        if (talentIndex === -1) break;

        this.tree.specTree.apexTalent.leftClick(talentIndex, true);
      }
      this.tree.talentCalculator.renderer.updateApexTalentIcon(this.tree.specTree);
      this.tree.talentCalculator.renderer.updateApexTalentPointCounter(this.tree.specTree.apexTalent);
    }
  }

  /**
   * generates the export string.
   * 
   * @returns {string} The generated export string.
   */
  exportString() {
    let writer = new BinaryArrayWriter(base64Table);
    // Serialization Version = 2 on 8 bits
    writer.write(2, 8)
    // Spec ID on 16 bits
    writer.write(this.tree.specData.id, 16);
    // Internal Blizzard hash, not used, can be set to 0 on 128 bits
    writer.write(0, 128);

    let nodeIds = this.tree.allNodeIds;
    nodeIds.forEach(nodeId => {
      let node = this.tree.getNodeFromId(nodeId);
      if (!node) {
        if (nodeId == this.tree.heroMetaNodeId) {
          if (MIDNIGHTDEBUG) console.log(`${nodeId}: hero Node (${this.tree.heroTreeChosen})`);
          // the root node for the hero tree is to be handled as a normal choice node: selected, purchased, maxed out, with 0 chosen if left tree or 1 if right tree
          if (this.tree.heroTree) {
            writer.write(1, 1); // isSelectedNode
            writer.write(1, 1); // isPurchasedNode
            writer.write(0, 1); // isPartiallyRankedNode
            writer.write(1, 1); // isChoiceNode
            writer.write(this.tree.heroTreeChosen, 2);
          } else {
            // if the hero tree hasn't been initialized, it means we haven't selected a hero tree and so the meta node is not selected
            writer.write(0, 1); //is SelectedNode
          }
        } else if (this.tree.heroRootNodeIds.includes(nodeId) && false) {
          // Root node of the tree that wasn't selected. Blizzard always has it as selected but not purchased
          // // Only true for Beta. Wrong for retail
          writer.write(1, 1); // isSelectedNode
          writer.write(0, 1); // isPurchasedNode
        } else if (this.tree.apexTalentId === nodeId) {
          const apexNode = this.tree.specTree.apexTalent;
          if (apexNode.usedPoints === 0) {
            writer.write(0, 1); // isSelectedNode
          } else if (apexNode.usedPoints < 4) {
            writer.write(1, 1); // isSelectedNode
            writer.write(1, 1); // isPurchasedNode
            writer.write(1, 1); // isPartiallyRankedNode
            writer.write(apexNode.usedPoints, 6); // investedPoints
            writer.write(0, 1); // isChoiceNode
          } else {
            writer.write(1, 1); // isSelectedNode
            writer.write(1, 1); // isPurchasedNode
            writer.write(0, 1); // isPartiallyRankedNode
            writer.write(0, 1); // isChoiceNode
          }
        } else {
          if (MIDNIGHTDEBUG) console.log(`${nodeId}: not selected`);
          writer.write (0, 1); // isSelectedNode
        }
      } else {
        if (node.state == 'permanentlyMaxedOut') {
          if (MIDNIGHTDEBUG) console.log(`${nodeId}: permanently maxed out`);
          writer.write(1, 1); // isSelectedNode
          writer.write(0, 1); // isPurchasedNode
        } else if (node.state == 'inactive' || (node.state == 'active' && node.currentPoints == 0)) {
          if (MIDNIGHTDEBUG) console.log(`${nodeId}: not selected`);
          writer.write(0, 1); // isSelectedNode
        } else if (node.getNodeType() == 'choice') {
          if (MIDNIGHTDEBUG) console.log(`${nodeId}: choice (${node.activeChoice})`);
          writer.write(1, 1); // isSelectedNode
          writer.write(1, 1); // isPurchasedNode
          writer.write(0, 1); // isPartiallyRankedNode
          writer.write(1, 1); // isChoiceNode
          writer.write(node.activeChoice, 2);
        } else if (node.state == 'active' && node.currentPoints < node.maxPoints) {
          if (MIDNIGHTDEBUG) console.log(`${nodeId}: selected (${node.currentPoints} / ${node.maxPoints})`);
          writer.write(1, 1); // isSelectedNode
          writer.write(1, 1); // isPurchasedNode
          writer.write(1, 1); // isPartiallyRankedNode
          writer.write(node.currentPoints, 6); // investedPoints
          writer.write(0, 1); // isChoiceNode
        } else { // node.state == maxedOut
          if (MIDNIGHTDEBUG) console.log(`${nodeId}: maxed out`);
          writer.write(1, 1); // isSelectedNode
          writer.write(1, 1); // isPurchasedNode
          writer.write(0, 1); // isPartiallyRankedNode
          writer.write(0, 1); // isChoiceNode
        }
      }
    });
    return writer.toExportString();
  }
}

// Expose to window for cross-module access (Icy Veins JS is a plain script, not a module)
window.MidnightTalentCalculator = MidnightTalentCalculator;
window.MidnightTalentCalculatorJSON = MidnightTalentCalculatorJSON;
