#!/usr/bin/python
# -*- coding: utf-8 -*-

# Khmer Legacy to Khmer Unicode Conversion and Vice Versa
# Copyright(c) 2006-2008 Khmer Software Initiative
#               www.khmeros.info
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# See the LICENSE file for more details.
#
# Developed by:
#       Hok Kakada (hokkakada@khmeros.info)
#       Keo Sophon (keosophon@khmeros.info)
#       San Titvirak (titvirak@khmeros.info)
#       Seth Chanratha (sethchanratha@khmeros.info)
#
# This program takes input as unordered khmer unicode string and produce
# an organized khmer unicode string based on the rule:
# baseCharacter [+ [Robat/Shifter] + [Coeng*] + [Shifter] + [Vowel] + [Sign]]


import unittest

BASE = 1
VOWEL = 2
SHIFTER = 4     # is shifter (muusekatoan or triisap) characer
COENG = 8
SIGN = 16
LEFT = 32       # vowel appear on left side of base
WITHE = 64      # vowel can be combined with SRA-E
WITHU = 128     # vowel can be combined with SRA-U
POSRAA = 256    # can be under PO SraA
MUUS = 512      # shifter place on specific character
TRII = 1024     # shifter place on specific character
ROBAT = 2048    # is robat character

# important character to test in order to form a cluster
RO = unichr(0x179A)
PO = unichr(0x1796)
SRAAA = unichr(0x17B6)
SRAE = unichr(0x17C1)
SRAOE = unichr(0x17BE)
SRAOO = unichr(0x17C4)
SRAYA = unichr(0x17BF)
SRAIE = unichr(0x17C0)
SRAAU = unichr(0x17C5)
SRAII = unichr(0x17B8)
SRAU = unichr(0x17BB)
TRIISAP = unichr(0x17CA)
MUUSIKATOAN = unichr(0x17C9)
SA = unichr(0x179F)
SAMYOKSANNYA = unichr(0x17D0)
NYO = unichr(0x1789)
ZWSP = unichr(0x200B)


# possible combination for sra E
sraEcombining = {
    SRAII:SRAOE,
    SRAYA:SRAYA,
    SRAIE:SRAIE,
    SRAAA:SRAOO,
    SRAAU:SRAAU
    }


# list of khmer character in unicode table (start from 1780)
KHMERCHAR = [
    BASE,               # ក     0x1780
    BASE,               # ខ
    BASE,               # គ
    BASE,               # ឃ
    BASE,               # ង
    BASE,               # ច
    BASE,               # ឆ
    BASE,               # ជ
    BASE,               # ឈ
    BASE + MUUS,        # ញ
    BASE,               # ដ
    BASE,               # ឋ
    BASE,               # ឌ
    BASE,               # ឍ
    BASE,               # ណ
    BASE + POSRAA,      # ត
    BASE,               # ថ     0x1790
    BASE,               # ទ
    BASE,               # ធ
    BASE + POSRAA,      # ន
    BASE + MUUS,        # ប
    BASE,               # ផ
    BASE,               # ព
    BASE + POSRAA,      # ភ
    BASE,               # ម
    BASE + POSRAA,      # យ
    BASE + POSRAA,      # រ
    BASE + POSRAA,      # ល
    BASE + POSRAA,      # វ
    BASE,               #
    BASE,               #
    BASE + TRII,        # ស
    BASE + TRII,        # ហ     0x17A0
    BASE,               # ឡ
    BASE + TRII,        # អ
    BASE,               # អ
    BASE,               # អា
    BASE,               # ឥ
    BASE,               # ឦ
    BASE,               # ឧ
    BASE,               #
    BASE,               # ឩ
    BASE,               # ឪ
    BASE,               # ឫ
    BASE,               # ឬ
    BASE,               # ឭ
    BASE,               # ឮ
    BASE,               # ឯ
    BASE,               #       0x17B0
    BASE,               #
    BASE,               # ឲ
    BASE,               #
    0, 0,               #
    VOWEL + WITHE + WITHU,  # ា
    VOWEL + WITHU,          # ិ
    VOWEL + WITHE + WITHU,  # ី
    VOWEL + WITHU,          # ឹ
    VOWEL + WITHU,          # ឺ
    VOWEL,                  # ុ
    VOWEL,                  # ូ
    VOWEL,                  # ួ
    VOWEL + WITHU,          # ើ
    VOWEL + WITHE,          # ឿ
    VOWEL + WITHE,          # ៀ     0x17C0 
    VOWEL + LEFT,           # េ
    VOWEL + LEFT,           # ែ
    VOWEL + LEFT,           # ៃ
    VOWEL,                  # ោ
    VOWEL + WITHE,          # ៅ
    SIGN + WITHU,           # ំ
    SIGN,                   # ះ
    SIGN,                   # ៈ
    SHIFTER,                # ៉
    SHIFTER,                # ៊
    SIGN,                   # ់
    ROBAT,                  # ៌
    SIGN,                   # ៍
    SIGN,                   #
    SIGN,                   # ៏
    SIGN + WITHU,           # ័​​   0x17D0
    SIGN,                   #
    COENG,                  # ្
    SIGN                    #
    ]

def khmerType(uniChar):
    """input one unicode character; 
    output an integer which is the Khmer type of the character or 0"""
    if (type(uniChar) != unicode):
        raise TypeError('only accept one character')

    if (len(uniChar) != 1):
        raise TypeError('only accept one character, but ' + str(len(uniChar)) + ' chars found.')

    ch = ord(uniChar[0])
    if (ch >= 0x1780):
        ch -= 0x1780
        if (ch < len(KHMERCHAR)):
            return KHMERCHAR[ch]
    return 0


def reorder(sin):
    """
    take khmer unicode string in visual-based cluster and return the rule-based
    cluster based on:
    baseCharacter [+ [Robat/Shifter] + [Coeng*] + [Shifter] + [Vowel] + [Sign]]
    and if the input is not unicode, return what it is input.
    """
    if (type(sin) != unicode):
        raise TypeError('only accept unicode string')
    result = u''
    sinLimit = len(sin)-1
    i = -1
    while i < sinLimit:
        # flush cluster
        baseChar = ''
        robat = ''
        shifter1 = ''
        shifter2 = ''
        coeng1 = ''
        coeng2 = ''
        vowel = ''
        poSraA = False
        sign = ''
        keep = ''
        cluster = ''

        while i < sinLimit:
            i += 1
            sinType = khmerType(sin[i])

            if (sinType & BASE):
                if (baseChar):
                    # second baseChar -> end of cluster
                    i -= 1 # continue with the found character
                    break
                baseChar = sin[i]
                keep = ''
                continue

            elif (sinType & ROBAT):
                if (robat):
                    # second robat -> end of cluster
                    i -= 1 # continue with the found character
                    break
                robat = sin[i]
                keep = ''
                continue

            elif (sinType & SHIFTER):
                if (shifter1):
                    # second shifter -> end of cluster
                    i -= 1 # continue with the found character
                    break
                shifter1 = sin[i]
                keep = ''
                continue

            elif (sinType & SIGN):
                if (sign):
                    # second sign -> end of cluster
                    i -= 1 # continue with the found character
                    break
                sign = sin[i]
                keep = ''
                continue

            elif (sinType & COENG):
                if (i == sinLimit):
                    coeng1 = sin[i]
                    break
                # if it is coeng RO (and consonent is not blank), it must belong to next cluster
                # so finish this cluster
                if ((sin[i+1] == RO) and (baseChar)):
                    i -= 1
                    break
                # no coeng yet so dump coeng to coeng1
                if (coeng1 == ''):
                    coeng1 = sin[i : i+2]
                    i += 1
                    keep = ''
                # coeng1 is coeng RO, the cluster can have two coeng, dump coeng to coeng2
                elif (coeng1[1] == RO):
                    coeng2 = sin[i : i+2]
                    i += 1
                    keep = ''
                else:
                    i -= 1
                    break

            elif (sinType & VOWEL):
                if (vowel == ''):
                    # if it is sra E ES AI (and consonent is not blank), it must belong to next cluster,
                    # so finish this cluster
                    if ((sinType & LEFT) and (baseChar)):
                        i -= 1
                        break
                    # give vowel a value found in the unorganized cluster
                    vowel = sin[i]
                    keep = ''

                elif ((baseChar == PO) and (not poSraA) and ((sin[i] == SRAAA) 
                        or (vowel == SRAAA))):
                    poSraA = True
                    if vowel == SRAAA:
                        vowel = sin[i]
                        keep = ''

                else:
                    # test if sra E is follow by sin which could combine with the following
                    if (vowel == SRAE) and (sinType & WITHE):
                        # give vowel a real sra by eleminate leading sra E
                        vowel = sraEcombining[sin[i]]
                        keep = ''
                    
                    # test if vowel can be combine with sin[i] (e.g. sra U and sra I or vice versa)
                    elif ((vowel == SRAU and (sinType & WITHU)) or 
                          ((khmerType(vowel) & WITHU) and sin[i] == SRAU)):
                        # vowel is not Sra I, II, Y, YY, transfer value from sin[i] to vowel
                        if (not(khmerType(vowel) & WITHU)):
                            vowel = sin[i]
                        # select shifter1 base on specific consonants
                        if (baseChar and (khmerType(baseChar) & TRII)):
                            shifter1 = TRIISAP                        
                        else:
                            shifter1 = MUUSIKATOAN
                        # examine if shifter1 should move shifter2 (base on coeng SA)                       
                    elif (vowel == SRAE) and (sin[i] == SRAU):
                        if (baseChar and (khmerType(baseChar) & TRII)):
                            shifter1 = TRIISAP                        
                        else:
                            shifter1 = MUUSIKATOAN
                        
                    else:
                        # sign can't be combine -> end of cluster
                        i -= 1 # continue with the found character
                        break

            else:
                # other than khmer -> end of cluster
                # continue with the next character
                if (sin[i] == ZWSP):
                    # avoid breaking of cluster if meet zwsp
                    # and move zwsp to end of cluster
                    keep = ZWSP
                else:
                    keep = sin[i]
                    break
    # end of while loop

        # Organization of a cluster:
        if ((vowel == SRAU) and (sign) and (khmerType(sign) & WITHU)):
            # samyoksanha + sraU --> MUUS + samyoksanha
            if (sign == SAMYOKSANNYA):
                vowel = ''
                shifter1 = MUUSIKATOAN
        
        # examine if shifter1 should move shifter2 (base on coeng)
        if (shifter1 and coeng1):
            if (khmerType(coeng1[1]) & TRII):
                shifter2 = TRIISAP
                shifter1 = ''
            elif (khmerType(coeng1[1]) & MUUS):
                shifter2 = MUUSIKATOAN
                shifter1 = ''

        # examine if PO + sraA > NYO, this case can only determin 
        # here since it need all element
        # coeng2 is priority (if coeng2 exist, coeng1 is always coRO)
        underPoSraA = coeng2 or coeng1
        if (len(underPoSraA) == 2):
            underPoSraA = khmerType(underPoSraA[1]) & POSRAA     
            # test if coeng is allow under PO + SRAA
            if ((poSraA and (not underPoSraA) and vowel) or ((baseChar == PO) 
                    and (vowel == SRAAA) and (not underPoSraA))):
                # change baseChar to letter NYO
                baseChar = NYO
                if ((vowel == SRAAA) and (not poSraA)):
                    vowel = ''

        # PO + SraA + SraE
        if ((poSraA) and (vowel == SRAE)):
            # PO + sraA is not NYO and there is leading sraE they should be recombined
            vowel = SRAOO

        # Rule of cluster
        # if there are two coeng, ceong1 is always coRO so put it after coeng2
        cluster = baseChar + robat + shifter1 + coeng2 + coeng1 + shifter2 + vowel + sign
        result = result + cluster + keep

    return result


class TestReordering(unittest.TestCase):

    def testKhmerType(self):
        # make sure the types are correct
        self.assertEqual(khmerType(unichr(0x177F)), 0)
        self.assertEqual(khmerType(unichr(0x1780)) & BASE, BASE)
        self.assertEqual(khmerType(unichr(0x17B6)), VOWEL + WITHE + WITHU)
        self.assertEqual(khmerType(unichr(0x17C9)), SHIFTER)
        self.assertEqual(khmerType(unichr(0x17CB)), SIGN)
        self.assertEqual(khmerType(unichr(0x17D4)), 0)
        self.assertEqual(khmerType(unichr(0x17ff)), 0)

    def testReorder(self):
        # make sure it output correctly
        self.assertEqual(reorder(u'កករ'), u'កករ')
        # make sure it reorder SHIFTER
        self.assertEqual(reorder(u'បា៉'), u'ប៉ា')
        self.assertEqual(reorder(u'បូ៊'), u'ប៊ូ')
        # make sure sra A + OM produce same as sra OM + A
        self.assertEqual(reorder(u'របំា'), u'របាំ')
        self.assertEqual(reorder(u'របាំ'), u'របាំ')
        # make sure ceong go where is suppose to be
        self.assertEqual(reorder(u'្រកដាស្របដាល់កណ្ដាល'), u'ក្រដាសប្រដាល់កណ្ដាល')
        # correct leading sra E if there are allow sra (e.g. sra A)
        self.assertEqual(reorder(u'បេង្គាល ខាងេលី េសៀវេភៅ'), u'បង្គោល ខាងលើ សៀវភៅ')
        # correct muus or triisap
        self.assertEqual(reorder(u'សីុបុីអុី'), u'ស៊ីប៉ីអ៊ី')
        # correct muus or triisap 2
        self.assertEqual(reorder(u'សុីបុីអុី'), u'ស៊ីប៉ីអ៊ី')
        # correct use of shifter on coeng SA
        self.assertEqual(reorder(u'ន្សីុ'), u'ន្ស៊ី')
        # sra E is at the right place
        self.assertEqual(reorder(u'េគ្របែឡងគ្នា'), u'គេប្រឡែងគ្នា')
        # case PO + a + coeng
        self.assertEqual(reorder(u'បពា្ញា'), u'បញ្ញា')
        self.assertEqual(reorder(u'បព្ជាី'), u'បញ្ជី')
        self.assertEqual(reorder(u'កេ្រព្ជាាង'), u'កញ្ជ្រោង')
        # english text
        self.assertEqual(reorder(u'this is english text'), u'this is english text')
        self.assertEqual(reorder(u'ចំេពាះ'), u'ចំពោះ')
        self.assertEqual(reorder(u'្របឹក្សាធម្មនុពា្ញ'), u'ប្រឹក្សាធម្មនុញ្ញ')

        self.assertEqual(reorder(u'ៃហប៊ី'), u'ហៃប៊ី')
        self.assertEqual(reorder(u'បូពា៌'), u'បូព៌ា')
        self.assertEqual(reorder(u'បានេស្នី'), u'បានស្នើ')
        self.assertEqual(reorder(u'្K្ក្េ'), u'្K្ក្េ')
        self.assertEqual(reorder(u'្'), u'្')
        # ignore the zero width space seperation
        self.assertEqual(reorder(u'រ'+unichr(0x200B)+u'ដ្ឋ'+unichr(0x200B)+u'ាភិប'+unichr(0x200B)
                            +u'ាល'), u'រ'+unichr(0x200B)+u'ដ្ឋាភិបាល')
        self.assertEqual(reorder(u'េ្របី'), u'ប្រើ')
        
        self.assertEqual(reorder(u'ប្បុ័ង'), u'ប្ប៉័ង')
        self.assertEqual(reorder(u'ប្ប័ុង'), u'ប្ប៉័ង')
        
        self.assertEqual(reorder(u'េសីុប'), u'ស៊ើប')
        self.assertEqual(reorder(u'េបីុង'), u'ប៉ើង')
        self.assertEqual(reorder(u'េសុីប'), u'ស៊ើប')
        
        self.assertEqual(reorder(u'កំុេជា'), u'កុំជោ')

    def testShifter(self):
        self.assertEqual(reorder(u'៊៊'), u'៊៊')   # TWO SHIFTER

    def testSign(self):
        self.assertEqual(reorder(u'ះះ'), u'ះះ')   # TWO SIGN

    def testAllCase(self):
        # resturn two cluster if two bases input
        self.assertEqual(reorder(u'កង'), u'កង')
        # resturn two cluster if two robats input
        self.assertEqual(reorder(u'៌៌'), u'៌៌')
        # resturn two cluster if two shifters input
        self.assertEqual(reorder(u'៉៊'), u'៉៊')
        # ceong ro is belong to the next cluster
        self.assertEqual(reorder(u'ប្រក'), u'បក្រ')
        # two ceongs: coeng ro is the sencode ceong
        self.assertEqual(reorder(u'្រស្ត'), u'ស្ត្រ')

    def testKhmerTypeError(self):
        self.assertRaises(TypeError, khmerType, 'KA')
        self.assertRaises(TypeError, khmerType, 1)
        self.assertRaises(TypeError, khmerType, {1:1})

    def testReorderError(self):
        self.assertRaises(TypeError, reorder, 'this is ansi')

if __name__ == '__main__':
    unittest.main()
