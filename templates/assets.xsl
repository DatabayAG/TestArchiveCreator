<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl">
    <xsl:output method="xml" version="1.0" encoding="UTF-8"/>
    <xsl:param name="service_version" select="0"/>

    <!--  Basic rule: copy everything not specified and process the children -->
    <xsl:template match="@*|node()">
        <xsl:copy><xsl:apply-templates select="@*|node()" /></xsl:copy>
    </xsl:template>

    <!-- General attributes with URLs -->

    <xsl:template match="@href">
        <xsl:attribute name="href">
            <xsl:value-of select="php:function('ilTestArchiveCreatorAssets::processUrl', string(.))"/>
        </xsl:attribute>
    </xsl:template>

    <xsl:template match="@icon">
        <xsl:attribute name="icon">
            <xsl:value-of select="php:function('ilTestArchiveCreatorAssets::processUrl', string(.))"/>
        </xsl:attribute>
    </xsl:template>

    <xsl:template match="@src">
        <xsl:attribute name="src">
            <xsl:value-of select="php:function('ilTestArchiveCreatorAssets::processUrl', string(.))"/>
        </xsl:attribute>
    </xsl:template>

    <!-- Element specific attributes with links -->

    <xsl:template match="object/@data">
        <xsl:attribute name="data">
            <xsl:value-of select="php:function('ilTestArchiveCreatorAssets::processUrl', string(.))"/>
        </xsl:attribute>
    </xsl:template>

    <xsl:template match="object/@codebase">
        <xsl:attribute name="codebase">
            <xsl:value-of select="php:function('ilTestArchiveCreatorAssets::processUrl', string(.))"/>
        </xsl:attribute>
    </xsl:template>


</xsl:stylesheet>