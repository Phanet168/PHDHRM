#!/usr/bin/python
# -*- coding: utf8 -*-

# Khmer Unicode to Khmer Legacy fonts Conversion
# Copyright(c) 2006-2008 Khmer Software Initiative
#               www.khmeros.info

# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# See the LICENSE file for more details.
#
# Developed by:
#       Jens Herden (jens@khmeros.info)


import string
import sys
import unittest
from types import *
from xml.dom.minidom import parse

# Python 2.3 only has sets as a module
try:
    foo = set()
    del(foo)
except: 
    from sets import Set as set

MAXUNI = 0x7f  # length of unicode table 
MAXLEG = 0x100 # length of legacy table
MAXLENGTH = 10 # maximun length of allowed unicode replacement
LEGSEP = ";" # separator for legacy attributes


def beautify(fontname):
    """lowercase and no dash, no underscore"""
    return fontname.lower().replace("-", " ").replace("_", " ")


class FontData:
    """ reads the fontdata from an XML file into a DOM tree
        but creates the data structures for the fonts only on demand """

    # cache for the font data
    legacyFontData = None
    unicodeFontData = None   
    # maps fontnames to fonttypes, which are keys in legacyFontData & unicodeFontData
    fontNames = None
    # maps fonttypes to DOM tree elements for reading on demand
    fontElements = None
    # maps fonttypes to its parents
    parents = None
    
    class Error(Exception):
        """ base class for exception from this class"""
        pass

    class XMLDataError(Error):
        """ an exception for errors in the internal structure of the XML file"""
        pass

    class FontNotFoundError(Error):
        """ an exception for errors when the wanted font is not available """
        pass

    def __init__(self):
        """ constructor reads the xml file into class variables """
        # only read if variables are empty
        if (not FontData.fontNames):
            self.readXML("fontdata.xml")

    def listFontTypes(self):
        """return sorted list of font types: ("abc-zwsp", "abc family", "baidok family", "limon family", "fk family", "truth family", "khek family", ...) """
        types = list(set(FontData.fontNames.values()))
        types.sort()
        return types

    def listFontNames(self):
        """return sorted list of all known font names ("Limon S1", "Baidok3c", ...) """
        names = FontData.fontNames.keys()
        names.sort()
        return names

    def listFontNamesForType(self, fonttype):
        """return sorted list of all known font names for a font type """
        nameList = []
        for name, type in FontData.fontNames.iteritems():
            if ((type == fonttype) and (beautify(type) != name)):
                nameList.append(name)
        nameList.sort()
        return nameList
    
    def typeForFontname(self, fontname):
        """ return fonttype for fontname  """
        name = beautify(fontname)
        if (not FontData.fontNames.has_key(name)):
            raise self.FontNotFoundError("Font: " + name + " is unknown.")
        return FontData.fontNames[name]

    def isConvertable(self, fontname):
        """return True if fontname is known, else return False"""
        try:
            self.typeForFontname(fontname)
        except:
            return False
        return True
        
    def defaultFont(self, fonttype):
        """return default font name according to fontname"""
        if not FontData.fontElements.has_key(fonttype):
            return fonttype
        element = FontData.fontElements[fonttype]
        fontname = element.getAttribute("default")
        if (fontname):
            return fontname
        return fonttype

    def unicodeData(self, fontname):
        """return data for unicode FontData according to fontname"""
        try:
            fonttype = self.typeForFontname(fontname)
        except self.FontNotFoundError:
            raise
            
        # read if data not available
        if (not FontData.unicodeFontData.has_key(fonttype)):
            self.__readUnicodeData(fonttype)

        return FontData.unicodeFontData[fonttype]

    def legacyData(self, fontname):
        """return data for legacy FontData according to fontname"""
        try:
            fonttype = self.typeForFontname(fontname)
        except self.FontNotFoundError:
            raise

        # read if data not available
        if (not FontData.legacyFontData.has_key(fonttype)):
            self.__readLegacyData(fonttype)

        return FontData.legacyFontData[fonttype]

    # List and Check Encoding
    encodingData = ["cp1252", "utf-8", "latin-1", "iso-8859-1"]
    
    def listEncodingTypes(self):
        """return list of encodingData for display"""
        return ["Plain Text (cp1252)", "Plain Text (latin-1/iso-8859-1)", "Unicode (utf-8)"]

    def canDecode(self, encoding):
        """return True if encoding is in encodingData, else return False"""
        return encoding.lower() in self.encodingData

    # convert from other encoding to cp1252
    def changeEncoding(self, sin, encoding):
        """if encoding is in encodingData but not cp1252,
            change encoding to cp1252 if
            return sin """
        if (self.canDecode(encoding) and encoding != 'cp1252'):
            try:                
                sin = sin.decode(encoding, 'ignore')
                sin = sin.encode('cp1252', 'ignore')
            except UnicodeEncodeError:
                    raise TypeError("Codecs Error")
        return sin
        
    def __decodeLegacy(self, attribute):
        """convert the legacy attribute from number to string"""
        s = ''
        l = string.split(attribute, LEGSEP);
        for piece in l:
            if len(piece) > 0:
                s += chr(eval(piece))
        return s #.encode('cp1252')

    def readXML(self, filename):
        try:
            datasource = open(filename)
        except IOError:
            try:
                datasource = open('modules/' + filename)
            except IOError:
                raise IOError('Cannot open ' + filename + ' for reading!')

        FontData.dom = parse(datasource)
        FontData.fontNames = dict()
        FontData.fontElements = dict()
        FontData.legacyFontData = dict()
        FontData.unicodeFontData = dict()   
        FontData.parents = dict()

        fonts = FontData.dom.getElementsByTagName("font")
        if (len(fonts) == 0):
            raise self.XMLDataError("no Fonts found in " + filename)

        for font in fonts:
            fonttype = font.getAttribute("type").lower()
            if (FontData.fontElements.has_key(fonttype)):
                raise self.XMLDataError("Font: " + fonttype + " is defined twice in " + filename)
            
            inherit = font.getAttribute("inherit").lower()
            if (inherit):
                if (not FontData.fontElements.has_key(inherit)):
                    raise self.XMLDataError("Font " + fonttype + " can not inherit unkown font " + inherit + " in " + filename)
                # map font to parent
                FontData.parents[fonttype] = inherit

            # map name to element
            FontData.fontElements[fonttype] = font
            hidden = (font.getAttribute("hidden").lower() == 'true')
            if (not hidden):
                # add default fonttype to known fontnames
                FontData.fontNames[beautify(fonttype)] = fonttype
                # add alias names 
                aliases = font.getElementsByTagName("alias")
                for alias in aliases:
                    FontData.fontNames[beautify(alias.getAttribute("name"))] = fonttype
                    
    def __readUnicodeData(self, fonttype):
        """ reads the unicode data for one font from the dom tree """
        if (not FontData.fontElements.has_key(fonttype)):
            raise self.FontNotFoundError("Font: " + fonttype + " is unknown.")
        font = FontData.fontElements[fonttype]

        # check and resolve inheritance
        if (FontData.parents.has_key(fonttype)):
            parent = FontData.parents[fonttype]
            # do we need to load the data?
            if (not FontData.unicodeFontData.has_key(parent)):
                self.__readUnicodeData(parent)
                
            # copy variables from parent
            unicodeDicts = list()
            for d in FontData.unicodeFontData[parent][0]:
                unicodeDicts.append(d.copy())
            unicodeTable = list(FontData.unicodeFontData[parent][1])
        else:
            # init variables
            unicodeDicts = list()
            unicodeTable = ["" for i in range(MAXUNI)]

        maps = font.getElementsByTagName("maps")
        if (len(maps) > 0):
            self.__readGlobalUni(maps[0], unicodeTable, unicodeDicts)
            self.__readFromUnicode(maps[0], unicodeDicts)

        FontData.unicodeFontData[fonttype] = (unicodeDicts, unicodeTable)

    def __readLegacyData(self, fonttype):
        """ reads the legacy data for one font from the dom tree """
        if (not FontData.fontElements.has_key(fonttype)):
            raise self.FontNotFoundError("Font: " + fonttype + " is unknown.")
        font = FontData.fontElements[fonttype]

        # check and resolve inheritance
        if (FontData.parents.has_key(fonttype)):
            parent = FontData.parents[fonttype]
            # do we need to load the data?
            if (not FontData.legacyFontData.has_key(parent)):
                self.__readLegacyData(parent)
                
            # copy variables from parent
            legacyDict = FontData.legacyFontData[parent][0].copy()
            legacyTable = list(FontData.legacyFontData[parent][1])
        else:
            # init variables
            legacyDict = dict()
            legacyTable = [unichr(i) for i in range(MAXLEG)]

        maps = font.getElementsByTagName("maps")
        if (len(maps) > 0):
            self.__readGlobal(maps[0], legacyTable, legacyDict)
            self.__readToUnicode(maps[0], legacyDict)

        FontData.legacyFontData[fonttype] = [legacyDict, legacyTable]

    def __readToUnicode(self, element, legacyDict):
        """ read the legacy replacements """
        maps = element.getElementsByTagName("tounicode")
        if (len(maps) < 1):
            return

        for map in maps[0].getElementsByTagName("map"):
            unicode = map.getAttribute("unicode")
            legacy = self.__decodeLegacy(map.getAttribute("legacy").encode("cp1252"))
            l = len(legacy)
            if (l > 0 and l < MAXLENGTH):
                if (not legacyDict.has_key(legacy)):
                    legacyDict[legacy] = unicode
                else:
                    raise self.XMLDataError("Legacy character " + legacy + " defined twice in toUnicode.")

    def __readFromUnicode(self, element, unicodeDicts):
        """ read the unicode replacements """
        maps = element.getElementsByTagName("fromunicode")
        if (len(maps) < 1):
            return

        for map in maps[0].getElementsByTagName("map"):
            unicode = map.getAttribute("unicode")
            legacy = self.__decodeLegacy(map.getAttribute("legacy"))
            l = len(unicode)
            if (l > 0 and l < MAXLENGTH):
                self.__addToUniData(unicode, legacy, unicodeDicts)


    def __readGlobalUni(self, element, unicodeTable, unicodeDicts):
        """ read the global replacements for unicode """
        maps = element.getElementsByTagName("global")
        if (len(maps) < 1):
            return

        for map in maps[0].getElementsByTagName("map"):
            unicode = map.getAttribute("unicode")
            legacy = self.__decodeLegacy(map.getAttribute("legacy"))
            l = len(unicode)
            if (l == 1):
                i = ord(unicode) - 0x1780
                if (i >= 0 and i < MAXUNI):
                    if (unicodeTable[i] == ""):
                        unicodeTable[i] = legacy
                    else:
                        raise self.XMLDataError("Unicode character " + ord(unicode).__hex__() + " defined twice in global.")
                else:
                    self.__addToUniData(unicode, legacy, unicodeDicts)
            else:
                if (l > 1 and l < MAXLENGTH):
                    self.__addToUniData(unicode, legacy, unicodeDicts)

    def __readGlobal(self, element, legacyTable, legacyDict):
        """ read the global replacements for legacy """
        maps = element.getElementsByTagName("global")
        if (len(maps) < 1):
            return

        for map in maps[0].getElementsByTagName("map"):
            legacy = self.__decodeLegacy(map.getAttribute("legacy").encode("cp1252"))
            unicode = map.getAttribute("unicode")
            l = len(legacy)
            if (l == 1):
                i = ord(legacy)
                if (i >= 0 and i < MAXLEG):
                    if (legacyTable[i] == unichr(i)):
                        legacyTable[i] = unicode
                    else:
                        raise self.XMLDataError("Legacy character " + i.__hex__() + " defined twice in global.")
            elif (l > 0 and l < MAXLENGTH):
                if (not legacyDict.has_key(legacy)):
                    legacyDict[legacy] = unicode
                else:
                    raise self.XMLDataError("Legacy character " + legacy + " defined twice in global.")


    def __addToUniData(self, unicode, legacy, data):
        """ put the unicode to legacy mapping in the right dict.
            data will get new dicts if needed """
        l = len(unicode)
        # sanity check 
        if (l > 0 and l < MAXLENGTH):
            # make sure we have enough dict's    
            while (len(data) < l):
                data.append(dict())
            # insert into dict
            if (not data[l - 1].has_key(unicode)):
                data[l - 1][unicode] = legacy
            else:
                raise self.XMLDataError("Unicode string " + unicode + " already in datastructure.")


# testing

class TestFontData(unittest.TestCase):

    dataClass = FontData()

    def setUp(self):
        self.dataClass.readXML("test-fontdata.xml")

    def testReadXML(self):
        self.assertRaises(IOError, self.dataClass.readXML, "afilethatdoesnotexist.xml")
        self.assertRaises(self.dataClass.XMLDataError, self.dataClass.readXML, "test-nofonts.xml")
        self.assertRaises(self.dataClass.XMLDataError, self.dataClass.readXML, "test-doublefonts.xml")
        self.assertRaises(self.dataClass.XMLDataError, self.dataClass.readXML, "test-inherit.xml")
        
    def testReadXML2(self):
        self.dataClass.readXML("test-doubleunicode.xml")
        self.assertRaises(self.dataClass.XMLDataError, self.dataClass.unicodeData, "abc")
        self.dataClass.readXML("test-doublelegacy.xml")
        self.assertRaises(self.dataClass.XMLDataError, self.dataClass.legacyData, "abc")
        self.dataClass.readXML("test-doubleunicode2.xml")
        self.assertRaises(self.dataClass.XMLDataError, self.dataClass.unicodeData, "abc")
        self.dataClass.readXML("test-doublelegacy2.xml")
        self.assertRaises(self.dataClass.XMLDataError, self.dataClass.legacyData, "abc")
        self.dataClass.readXML("test-doublelegacy3.xml")
        self.assertRaises(self.dataClass.XMLDataError, self.dataClass.legacyData, "abc")

    def testListFontNames(self):
        fonts = self.dataClass.listFontNames()
        self.assertEqual(len(fonts), 7)

    def testListFontTypes(self):
        fonts = self.dataClass.listFontTypes()
        self.assertEqual(len(fonts), 4)
        
    def testLegacyData(self):
        self.assertRaises(self.dataClass.FontNotFoundError, self.dataClass.legacyData, "unkownFontName%%%%%")
        # the font 'hidden' is in the XML but should not be visible
        self.assertRaises(self.dataClass.FontNotFoundError, self.dataClass.legacyData, "hidden")
        # do we get for all fonts data?
        fonts = self.dataClass.listFontNames()
        for font in fonts:
            data = self.dataClass.legacyData(font)
            self.assertEqual(len(data), 2)
            self.assertEqual(type(data[0]), DictType)
            self.assertEqual(type(data[1]), ListType)
        
    def testLegacyData2(self):
        # test specific fonts; 
        # abc-zwsp & abc-3 inherit from abc
        for font in ['abc', 'abc-3', 'abc-zwsp']:
            data = self.dataClass.legacyData(font)
            self.assertEqual(data[0]['b' + chr(255)], u"ឫ")
            self.assertEqual(data[1][ord("a")], u"កក")
            self.assertEqual(data[1][ord("b")], u"ស")
            self.assertEqual(data[1][ord("c")], unichr(0x200B))
        
        for font in ['abc-3', 'abc-zwsp']:
            data = self.dataClass.legacyData(font)
            self.assertEqual(data[1][ord("1")], u"១")
            self.assertEqual(data[0]['1?'.encode('cp1252')], "")

        # do we get for all fonts data?
        fonts = self.dataClass.listFontNames()
        for font in fonts:
            data = self.dataClass.legacyData(font)
            self.assertEqual(len(data), 2)
            self.assertEqual(type(data[0]), DictType)
            self.assertEqual(type(data[1]), ListType)
        
    def testUnicodeData(self):
        self.assertRaises(self.dataClass.FontNotFoundError, self.dataClass.unicodeData, "unkownFontName%%%%%")
        # the font 'hidden' is in the XML but should not be visible
        self.assertRaises(self.dataClass.FontNotFoundError, self.dataClass.unicodeData, "hidden")
        # do we get for all fonts data?
        fonts = self.dataClass.listFontNames()
        for font in fonts:
            data = self.dataClass.unicodeData(font)
            self.assertEqual(len(data), 2)
            self.assertEqual(type(data[0]), ListType)
            for d in data[0]:
                self.assertEqual(type(d), DictType)
            self.assertEqual(type(data[1]), ListType)

    def testUnicodeData2(self):
        # abc-zwsp & abc-3 inherit from abc
        for font in ['abc', 'abc-3', 'abc-zwsp']:
            data = self.dataClass.unicodeData(font)
            self.assertEqual(data[0][0][unichr(0x200B)], "c")
            self.assertEqual(data[0][1][u"កក"], "a")
            self.assertEqual(data[0][2][u"ខ្រ"], "__")
            self.assertEqual(data[1][ord(u"ស") - 0x1780], "b")
        
        for font in ['abc-3', 'abc-zwsp']:
            data = self.dataClass.legacyData(font)
            self.assertEqual(data[1][ord("1")], u"១")
            self.assertEqual(data[0]['1?'.encode('cp1252')], "")
        
    def testIsConvertable(self):
        self.failIf(self.dataClass.isConvertable("unkownFontName%%%%%%"))
        for font in self.dataClass.listFontNames():
            self.assert_(self.dataClass.isConvertable(font))
            self.assert_(self.dataClass.isConvertable(font.upper()))
            self.assert_(self.dataClass.isConvertable(font.replace(" ", "-")))
            self.assert_(self.dataClass.isConvertable(font.replace(" ", "_")))

    def testAddToUniData(self):
        unicode = u"abcDEFG"
        legacy = "yes"
        data = list()
        self.dataClass._FontData__addToUniData(unicode, legacy, data)
        self.assertEqual(type(data), ListType)
        self.assertEqual(len(data), len(unicode))
        self.assertEqual(data[len(unicode) - 1][unicode], legacy)

    def testFontdataxml(self):
        """ test that all data can be read without error """
        self.dataClass.readXML("fontdata.xml")
        fonts = self.dataClass.listFontNames()
        for font in fonts:
            print font
            self.dataClass.unicodeData(font)
            self.dataClass.legacyData(font)
        
    def testListFontNamesForType(self):
        """ test that list of font names is correct """
        self.dataClass.readXML("fontdata.xml")
        # the type should not be in the list of fonts for this type
        for fontType in self.dataClass.listFontTypes():
            fontList = self.dataClass.listFontNamesForType(fontType)
            for font in fontList:
                self.assert_(not font == fontType)

if __name__ == '__main__':
    unittest.main()
