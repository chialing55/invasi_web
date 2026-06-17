args <- commandArgs(trailingOnly = TRUE)
if (length(args) < 5) {
  stop("Usage: stats_charts.R <fig1.csv> <fig2.csv> <output.pdf> <chinese-font> <times.ttf>")
}

fig1_path <- args[[1]]
fig2_path <- args[[2]]
out_path <- args[[3]]
chinese_font_path <- args[[4]]
times_path <- args[[5]]

read_utf8_csv <- function(path) {
  if (!file.exists(path) || file.info(path)$size == 0) {
    return(data.frame())
  }

  lines <- readLines(path, encoding = "UTF-8", warn = FALSE)
  lines <- iconv(lines, from = "UTF-8", to = "UTF-8", sub = "")
  lines <- lines[!is.na(lines)]
  if (length(lines) == 0) {
    return(data.frame())
  }

  lines[1] <- sub("^\ufeff", "", lines[1])
  read.csv(text = paste(lines, collapse = "\n"), stringsAsFactors = FALSE, check.names = FALSE)
}

fig1 <- read_utf8_csv(fig1_path)
fig2 <- read_utf8_csv(fig2_path)

has_showtext <- requireNamespace("showtext", quietly = TRUE) && requireNamespace("sysfonts", quietly = TRUE)
if (!has_showtext) {
  stop("R packages showtext and sysfonts are required for Chinese PDF output. Please install them in the container.")
}

if (!file.exists(chinese_font_path) || !file.exists(times_path)) {
  stop("Chinese font file and times.ttf are required for chart PDF output.")
}

sysfonts::font_add("cjk", regular = chinese_font_path)
sysfonts::font_add("times", regular = times_path)
showtext::showtext_opts(dpi = 300)
showtext::showtext_auto()

open_pdf <- function(path) {
  grDevices::pdf(path, width = 8.8, height = 6.2, onefile = TRUE, family = "sans")
}

vlabels <- function(x) {
  vapply(strsplit(as.character(x), "", fixed = TRUE), paste, collapse = "\n", FUN.VALUE = character(1))
}

axis_text_family <- "cjk"
number_family <- "times"
y_axis_label <- "\u7269\n\u7a2e\n\u6578"
x_axis_label <- "\u79d1\u5225"
previous_label <- "\u524d\u6b21\u8abf\u67e5"
current_label <- "\u672c\u6b21\u8abf\u67e5"
empty_label <- "\u7121\u8cc7\u6599"
previous_color <- "#16697a"
current_color <- "#FF9905"

rounded_bar <- function(xleft, ybottom, xright, ytop, col, radius = 0.12) {
  if (is.na(ytop) || ytop <= ybottom) {
    return()
  }

  width <- xright - xleft
  height <- ytop - ybottom
  r <- min(radius, width / 2, height)
  theta_left <- seq(pi, pi / 2, length.out = 8)
  theta_right <- seq(pi / 2, 0, length.out = 8)
  xs <- c(
    xleft,
    xleft,
    xleft + r + r * cos(theta_left),
    xright - r + r * cos(theta_right),
    xright,
    xright
  )
  ys <- c(
    ybottom,
    ytop - r,
    ytop - r + r * sin(theta_left),
    ytop - r + r * sin(theta_right),
    ytop - r,
    ybottom
  )
  polygon(xs, ys, col = col, border = col, xpd = FALSE)
}

draw_rounded_bars <- function(mids, values, width, colors, radius = 0.12) {
  mids_vec <- as.vector(mids)
  values_vec <- as.vector(values)
  color_vec <- rep(colors, length.out = length(values_vec))

  for (i in seq_along(values_vec)) {
    rounded_bar(mids_vec[i] - width / 2, 0, mids_vec[i] + width / 2, values_vec[i], color_vec[i], radius)
  }
}
plot_empty <- function(message) {
  plot.new()
  text(0.5, 0.5, message, family = axis_text_family, cex = 1.4)
}

plot_fig1 <- function(df) {
  if (nrow(df) == 0) {
    plot_empty(empty_label)
    return()
  }
  names(df) <- c("family", "value")
  df$value <- as.numeric(df$value)
  ymax <- max(5, ceiling(max(df$value, na.rm = TRUE) / 5) * 5)
  if (ymax == max(df$value, na.rm = TRUE)) ymax <- ymax + 5

  par(mar = c(8.0, 5.6, 1.7, 1.7), oma = c(0.5, 0.5, 0.5, 0.5), family = axis_text_family)
  mids <- barplot(
    df$value,
    names.arg = rep("", nrow(df)),
    ylim = c(0, ymax),
    col = NA,
    border = NA,
    axes = FALSE,
    ylab = "",
    width = 0.35,
    space = 1.25
  )
  ticks <- seq(0, ymax, by = 5)
  abline(h = ticks, col = "#D9D9D9", lwd = 0.8)
  draw_rounded_bars(mids, df$value, 0.35, current_color, radius = 0.12)
  axis(2, at = ticks, las = 1, family = number_family, cex.axis = 0.9)
  axis(1, at = mids, labels = FALSE)
  text(mids, df$value + ymax * 0.025, labels = df$value, family = number_family, cex = 0.85)
  usr <- par("usr")
  text(usr[1] - diff(usr[1:2]) * 0.07, ymax * 0.5, labels = y_axis_label, xpd = NA, adj = c(0.5, 0.5), family = axis_text_family, cex = 0.95)
  text(mids, usr[3] - ymax * 0.08, labels = vlabels(df$family), xpd = NA, adj = c(0.5, 1), family = axis_text_family, cex = 0.92)
  text(mean(usr[1:2]), usr[3] - ymax * 0.30, labels = x_axis_label, xpd = NA, adj = c(0.5, 0.5), family = axis_text_family, cex = 0.95)
}

plot_fig2 <- function(df) {
  if (nrow(df) == 0) {
    plot_empty(empty_label)
    return()
  }
  names(df) <- c("family", "previous", "current")
  df$previous <- as.numeric(df$previous)
  df$current <- as.numeric(df$current)
  ymax <- max(5, ceiling(max(c(df$previous, df$current), na.rm = TRUE) / 5) * 5)
  if (ymax == max(c(df$previous, df$current), na.rm = TRUE)) ymax <- ymax + 5

  par(mar = c(9.6, 5.6, 1.7, 1.7), oma = c(0.5, 0.5, 0.5, 0.5), family = axis_text_family)
  values <- rbind(df$previous, df$current)
  mids <- barplot(
    values,
    beside = TRUE,
    names.arg = rep("", nrow(df)),
    ylim = c(0, ymax),
    col = NA,
    border = NA,
    axes = FALSE,
    ylab = "",
    width = 0.32,
    space = c(0.25, 1.0)
  )
  centers <- colMeans(mids)
  ticks <- seq(0, ymax, by = 5)
  abline(h = ticks, col = "#D9D9D9", lwd = 0.8)
  draw_rounded_bars(mids, values, 0.32, c(previous_color, current_color), radius = 0.12)
  axis(2, at = ticks, las = 1, family = number_family, cex.axis = 0.9)
  axis(1, at = centers, labels = FALSE)
  text(mids, values + ymax * 0.025, labels = values, family = number_family, cex = 0.75)
  usr <- par("usr")
  text(usr[1] - diff(usr[1:2]) * 0.07, ymax * 0.5, labels = y_axis_label, xpd = NA, adj = c(0.5, 0.5), family = axis_text_family, cex = 0.95)
  text(centers, usr[3] - ymax * 0.08, labels = vlabels(df$family), xpd = NA, adj = c(0.5, 1), family = axis_text_family, cex = 0.82)
  text(mean(usr[1:2]), usr[3] - ymax * 0.31, labels = x_axis_label, xpd = NA, adj = c(0.5, 0.5), family = axis_text_family, cex = 0.95)

  legend_y <- usr[3] - ymax * 0.42
  box_w <- diff(usr[1:2]) * 0.018
  box_h <- ymax * 0.035
  gap <- diff(usr[1:2]) * 0.018
  item_gap <- diff(usr[1:2]) * 0.13
  legend_total_w <- item_gap + box_w + gap + strwidth(current_label, cex = 0.95)
  legend_x <- mean(usr[1:2]) - legend_total_w / 2
  rect(legend_x, legend_y - box_h / 2, legend_x + box_w, legend_y + box_h / 2, col = "#16697a", border = "#16697a", xpd = NA)
  text(legend_x + box_w + gap, legend_y, labels = previous_label, xpd = NA, adj = c(0, 0.5), family = axis_text_family, cex = 0.95)
  legend_x2 <- legend_x + item_gap
  rect(legend_x2, legend_y - box_h / 2, legend_x2 + box_w, legend_y + box_h / 2, col = "#FF9905", border = "#FF9905", xpd = NA)
  text(legend_x2 + box_w + gap, legend_y, labels = current_label, xpd = NA, adj = c(0, 0.5), family = axis_text_family, cex = 0.95)
}

open_pdf(out_path)
plot_fig1(fig1)
plot_fig2(fig2)
dev.off()
