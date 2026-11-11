package com.arflix.tv.data.repository

import com.arflix.tv.data.model.StreamSource
import com.google.firebase.auth.FirebaseAuth
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import javax.inject.Inject
import javax.inject.Singleton
import kotlin.coroutines.resume

/**
 * Asks coovery.com.br for a video URL registered in the WordPress plugin.
 * The site only answers when the Firebase account is signed in.
 */
@Singleton
class CooveryFontesRepository @Inject constructor(
    private val okHttpClient: OkHttpClient
) {
    suspend fun movie(tmdbId: Int?): List<StreamSource> = load("movie", tmdbId, null, null)

    suspend fun episode(tmdbId: Int?, season: Int, episode: Int): List<StreamSource> =
        load("series", tmdbId, season, episode)

    private suspend fun load(
        type: String,
        tmdbId: Int?,
        season: Int?,
        episode: Int?
    ): List<StreamSource> {
        if (tmdbId == null || tmdbId <= 0) return emptyList()
        if (type == "series" && (season == null || season < 1 || episode == null || episode < 1)) {
            return emptyList()
        }
        return withTimeoutOrNull(8_000L) {
            withContext(Dispatchers.IO) {
                val token = firebaseToken() ?: return@withContext emptyList()
                val url = ENDPOINT.toHttpUrl().newBuilder()
                    .addQueryParameter("type", type)
                    .addQueryParameter("tmdb_id", tmdbId.toString())
                    .apply {
                        if (season != null && episode != null) {
                            addQueryParameter("season", season.toString())
                            addQueryParameter("episode", episode.toString())
                        }
                    }
                    .build()
                val request = Request.Builder()
                    .url(url)
                    .header("Authorization", "Bearer $token")
                    .build()
                runCatching {
                    okHttpClient.newCall(request).execute().use { response ->
                        if (!response.isSuccessful) return@use emptyList()
                        parse(response.body?.string().orEmpty())
                    }
                }.getOrDefault(emptyList())
            }
        }.orEmpty()
    }

    private suspend fun firebaseToken(): String? {
        val user = runCatching { FirebaseAuth.getInstance().currentUser }.getOrNull() ?: return null
        return suspendCancellableCoroutine { continuation ->
            user.getIdToken(false)
                .addOnSuccessListener { result ->
                    if (continuation.isActive) continuation.resume(result.token?.takeIf { it.isNotBlank() })
                }
                .addOnFailureListener {
                    if (continuation.isActive) continuation.resume(null)
                }
        }
    }

    private fun parse(body: String): List<StreamSource> {
        val streams = JSONObject(body).optJSONArray("streams") ?: return emptyList()
        return buildList {
            for (index in 0 until streams.length()) {
                val item = streams.optJSONObject(index) ?: continue
                val url = item.optString("url").trim()
                if (!url.startsWith("https://") && !url.startsWith("http://")) continue
                val title = item.optString("title").trim().ifBlank { "Coovery" }
                add(
                    StreamSource(
                        source = title,
                        addonName = "Coovery",
                        addonId = "coovery-fontes",
                        quality = "",
                        size = "",
                        url = url
                    )
                )
            }
        }
    }

    private companion object {
        const val ENDPOINT = "https://coovery.com.br/wp-json/coovery/v1/stream"
    }
}
