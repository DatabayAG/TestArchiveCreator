<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl">
    <xsl:output method="xml" version="1.0" encoding="UTF-8"/>
    <xsl:param name="service_version" select="0"/>

    <!--  Basic rule: copy everything not specified and process the children -->
    <xsl:template match="@*|node()">
        <xsl:copy><xsl:apply-templates select="@*|node()" /></xsl:copy>
    </xsl:template>

    <xsl:template match="head/link/@href">
        <xsl:attribute name="href">
            <xsl:value-of select="php:function('ilTestArchiveCreatorAssets::processUrl', string(.))"/>
        </xsl:attribute>
    </xsl:template>

</xsl:stylesheet>