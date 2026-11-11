package com.arflix.tv.ui.screens.player

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class PlaybackBufferProfileTest {
    @Test
    fun `android tv sizes its forward buffer like a phone`() {
        val heap = playbackBufferMemoryClassMb(
            memoryClassMb = 192,
            largeMemoryClassMb = 512,
            isLowRamDevice = false,
            isTvDevice = true
        )
        assertEquals(512, heap)
        val profile = buildPlaybackBufferProfile(heap, isLowRamDevice = false, isTvDevice = true)
        val phone = buildPlaybackBufferProfile(512, isLowRamDevice = false, isTvDevice = false)
        assertEquals(phone.maxBufferMs, profile.maxBufferMs)
        assertEquals(phone.targetBufferBytes, profile.targetBufferBytes)
        assertEquals(phone.bufferForPlaybackMs, profile.bufferForPlaybackMs)
    }

    @Test
    fun `a low ram tv keeps the small buffer`() {
        val heap = playbackBufferMemoryClassMb(
            memoryClassMb = 192,
            largeMemoryClassMb = 512,
            isLowRamDevice = true,
            isTvDevice = true
        )
        assertEquals(192, heap)
    }

    @Test
    fun `tv forward buffer stays inside the byte capped range`() {
        val heap = playbackBufferMemoryClassMb(
            memoryClassMb = 128,
            largeMemoryClassMb = 1024,
            isLowRamDevice = false,
            isTvDevice = true
        )
        assertEquals(512, heap)
        val profile = buildPlaybackBufferProfile(heap, isLowRamDevice = false, isTvDevice = true)
        assertTrue(!profile.prioritizeTimeOverSizeThresholds)
    }

    @Test
    fun `byte limited devices reserve allocator capacity for forward playback`() {
        for (memoryMb in listOf(128, 256, 384, 512, 768, 1024)) {
            for (lowRam in listOf(true, false)) {
                val profile = buildPlaybackBufferProfile(memoryMb, lowRam, isTvDevice = true)
                if (!profile.prioritizeTimeOverSizeThresholds) {
                    val previousLimit = when {
                        lowRam || memoryMb <= 256 -> 2_000
                        memoryMb <= 384 -> 3_000
                        else -> 5_000
                    }
                    assertEquals(previousLimit, profile.backBufferMs)
                    assertTrue(profile.targetBufferBytes > 0)
                }
            }
        }
    }

    @Test
    fun `time prioritized devices keep a twenty second rewind in memory`() {
        val profile = buildPlaybackBufferProfile(1024, isLowRamDevice = false, isTvDevice = true)
        assertTrue(profile.prioritizeTimeOverSizeThresholds)
        assertEquals(20_000, profile.backBufferMs)
    }
}
