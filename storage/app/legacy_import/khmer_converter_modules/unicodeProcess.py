#!/usr/bin/python
# -*- coding: utf-8 -*-

# Khmer Legacy fonts to Khmer Unicode Conversion
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


import unittest
import sys
from types import *


def process(sin, data):
    """convert from legacy to unicode
    sin : string input as legacy encoding
    data: list for legacy to unicode conversion
    return value: unicode string
    """
    if (data == None or type(data) != ListType or len(data) != 2 or type(data[0]) != DictType or type(data[1]) != ListType):
        raise TypeError("Wrong data for conversion.")

    if (type(sin) == unicode):
        raise TypeError("Input must not be Unicode string.")
        
    condenseData = data[0] # dictionary with character combinations and replacements
    replaceData = data[1] # list with character replacement values
    sout = u''
    listLength = len(replaceData)
    i = 0
    end = len(sin)
    while (i < end):
        for key in condenseData.keys():
            if (key == sin[i : i+len(key)]):
                sout += condenseData[key]
                i += len(key)
                break
        else:
            n = ord(sin[i])
            if (n < listLength):
                sout += replaceData[n]
            else:
                sout += unichr(n)
            i += 1
    return sout



class TestProcessing(unittest.TestCase):

    def setUp(self):
        self.data = [
            {
            "12":u"_", 
            u"b¤".encode("cp1252"):u"ឬ",
            u"B£".encode("cp1252"):u"ឭ",
            u"B¤".encode("cp1252"):u"ឮ",
            "abcd":u""
            },
            [u"*", u"cbc", u"ក", u"កគ", u""]
            ]

    def testConversion(self):
        # make sure conversions works like expected
        self.assertEqual(process(chr(0), self.data), u"*")
        self.assertEqual(process(chr(1), self.data), u"cbc")
        self.assertEqual(process(chr(2), self.data), u"ក")
        self.assertEqual(process(chr(3), self.data), u"កគ")
        self.assertEqual(process(chr(4), self.data), u"")
        self.assertEqual(process(chr(3) + chr(0), self.data), u"កគ*")
        
    def testInvalid(self):
        # make sure conversions does not break
        self.assertEqual(process(unichr(255).encode('cp1252'), self.data), unichr(255))
        self.assertEqual(process(unichr(len(self.data[1])).encode('cp1252'), self.data), unichr(len(self.data[1])))

    def testTypeError(self):
        #make sure module will raise TypeError when data is wrong
        self.assertRaises(TypeError, process,'sala', None)
        self.assertRaises(TypeError, process,'sala', 1)

    def testCondense(self):
        self.assertEqual(process('12'.encode('cp1252'), self.data), u"_")
        self.assertEqual(process('1212'.encode('cp1252'), self.data), u"__")
        self.assertEqual(process('12x12'.encode('cp1252'), self.data), u"_x_")
        self.assertEqual(process(u'b¤'.encode('cp1252'), self.data), u"ឬ")
        self.assertEqual(process(u'b¤B£B¤'.encode('cp1252'), self.data), u"ឬឭឮ")
        self.assertEqual(process('abcd', self.data), u"")

if __name__ == '__main__':
    unittest.main()
