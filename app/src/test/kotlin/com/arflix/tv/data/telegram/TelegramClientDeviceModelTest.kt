package com.arflix.tv.data.telegram

import com.arflix.tv.util.DeviceType
import com.google.common.truth.Truth.assertThat
import org.junit.Test

class TelegramClientDeviceModelTest {

    @Test
    fun tvWithBlankModelReturnsArvioTv() {
        val model = resolveTelegramDeviceModel(DeviceType.TV, rawModel = "")
        assertThat(model).isEqualTo("COOVERY TV")
    }

    @Test
    fun tvWithGenericModelReturnsArvioTv() {
        assertThat(resolveTelegramDeviceModel(DeviceType.TV, rawModel = "Android TV")).isEqualTo("COOVERY TV")
        assertThat(resolveTelegramDeviceModel(DeviceType.TV, rawModel = "Google TV")).isEqualTo("COOVERY TV")
        assertThat(resolveTelegramDeviceModel(DeviceType.TV, rawModel = "generic")).isEqualTo("COOVERY TV")
        assertThat(resolveTelegramDeviceModel(DeviceType.TV, rawModel = "unknown")).isEqualTo("COOVERY TV")
        assertThat(resolveTelegramDeviceModel(DeviceType.TV, rawModel = "sdk_gphone64_arm64")).isEqualTo("COOVERY TV")
        assertThat(resolveTelegramDeviceModel(DeviceType.TV, rawModel = "Android SDK built for x86")).isEqualTo("COOVERY TV")
    }

    @Test
    fun tvWithSpecificHardwareModelAppendsModelAndTvTag() {
        val model = resolveTelegramDeviceModel(DeviceType.TV, rawModel = "Chromecast")
        assertThat(model).isEqualTo("COOVERY TV (Chromecast)")
    }

    @Test
    fun tvWithModelAlreadyContainingTvDoesNotDuplicateTv() {
        val model = resolveTelegramDeviceModel(DeviceType.TV, rawModel = "SHIELD Android TV")
        assertThat(model).isEqualTo("COOVERY (SHIELD Android TV)")
    }

    @Test
    fun tvWithFireStickHardwareModel() {
        val model = resolveTelegramDeviceModel(DeviceType.TV, rawModel = "AFTMM")
        assertThat(model).isEqualTo("COOVERY TV (AFTMM)")
    }

    @Test
    fun tvWithCustomDeviceNameTakesPrecedence() {
        val model = resolveTelegramDeviceModel(
            deviceType = DeviceType.TV,
            rawModel = "Chromecast",
            customDeviceName = "Living Room TV"
        )
        assertThat(model).isEqualTo("COOVERY (Living Room TV)")
    }

    @Test
    fun tvWithGenericCustomDeviceNameFallsBackToModel() {
        val model = resolveTelegramDeviceModel(
            deviceType = DeviceType.TV,
            rawModel = "Chromecast",
            customDeviceName = "Android TV"
        )
        assertThat(model).isEqualTo("COOVERY TV (Chromecast)")
    }

    @Test
    fun tabletReturnsArvioTablet() {
        assertThat(resolveTelegramDeviceModel(DeviceType.TABLET, rawModel = ""))
            .isEqualTo("COOVERY Tablet")
        assertThat(resolveTelegramDeviceModel(DeviceType.TABLET, rawModel = "SM-X800"))
            .isEqualTo("COOVERY Tablet (SM-X800)")
    }

    @Test
    fun phoneReturnsArvioWithModel() {
        assertThat(resolveTelegramDeviceModel(DeviceType.PHONE, rawModel = ""))
            .isEqualTo("COOVERY")
        assertThat(resolveTelegramDeviceModel(DeviceType.PHONE, rawModel = "Pixel 8"))
            .isEqualTo("COOVERY (Pixel 8)")
    }

    @Test
    fun modelAlreadyContainingArvioIsPreserved() {
        assertThat(resolveTelegramDeviceModel(DeviceType.TV, rawModel = "COOVERY TV Stick"))
            .isEqualTo("COOVERY TV Stick")
    }
}
